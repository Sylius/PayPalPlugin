# UPGRADE FROM 2.0 to 2.1

1. #### The shop-facing PayPal button placements now run on PayPal's Web SDK v6.

   The product page, cart page, and checkout payment page placements no longer load PayPal's v5 JS SDK
   (`paypal.js?...&disable-funding=...`) with `paypal.Buttons()`. They now load `web-sdk/v6/core`, create one SDK
   instance per page, gate visibility through `findEligibleMethods()`, and render PayPal's own web components
   (`<paypal-button>`) instead of building buttons from JavaScript.

   **This is a template/asset restructuring, not a PHP public API change** — templates and compiled assets are
   not covered by this package's BC promise, and Sylius core itself ships template restructuring in patch
   releases. However, if your shop overrode `pay_from_cart_page.html.twig`, `pay_from_product_page.html.twig`,
   or `pay_from_payment_page.html.twig`, **that override now silently renders v5 markup with no SDK loaded
   behind it** — the button will not appear, or will appear inert, with no error. Diff your override against
   the new templates in this release and update it, or remove the override if it's no longer needed.

1. #### The button JavaScript now ships as a Stimulus controller, which your shop has to build.

   The placements render `data-controller="sylius--paypal-plugin--paypal-web-sdk"` instead of an inline
   `<script>` block. The controller lives in this package's own npm package (`assets/shop`), so **an existing
   shop will render the attribute and nothing will happen — no button, no error — until the package is part of
   your asset build.** Two steps, both one-time:

   1. Add the package to your app's `package.json`:

      ```json
      "dependencies": {
          "@sylius/paypal-plugin": "file:vendor/sylius/paypal-plugin/assets/shop"
      }
      ```

   2. Register the controller in whichever `controllers.json` your shop build passes to
      `Encore.enableStimulusBridge()` (typically `assets/shop/controllers.json`):

      ```json
      "@sylius/paypal-plugin": {
          "paypal-web-sdk": { "enabled": true, "fetch": "lazy" }
      }
      ```

   Then `yarn install && yarn build`. Verify by loading a product page and checking that the browser fetches
   the controller chunk and the PayPal button loses its `hidden` attribute.

1. #### The PayPal app behind your client id has to have the `JavaScript SDK v6` feature enabled.

   Web SDK v6 is gated per REST app, and an app created before it existed does not have the feature. Its
   client id keeps working everywhere else, so nothing in the shop says anything is wrong: the server-side
   REST calls — onboarding, creating the order, capturing it, refunds — are unaffected, and no shop log
   records the failure. **What breaks is the browser**: `findEligibleMethods()` answers
   `403 NOT_AUTHORIZED` / `PERMISSION_DENIED`, the SDK throws `SdkInitError`, and every placement gives up
   in its `catch` block with nothing but a `console.error`:

   - the product and cart buttons stay `hidden`, exactly as they do when the Stimulus controller is missing
     from the asset build;
   - the checkout payment step renders **neither the wallet button nor the card fields** — both controllers
     share one memoized SDK session, so the rejected one takes down both;
   - Pay Later messaging stays hidden.

   Enable the feature on the app in the PayPal Developer Dashboard. Sandbox and live are separate apps, so
   it has to be enabled on each of them, and a shop that pastes sandbox credentials by hand needs it on the
   app those credentials come from. Verify it the same way as the asset build above: load a product page and
   check that the button loses its `hidden` attribute and that the console carries no `403`.

1. #### PayPal now offers the shop's real shipping methods inside the wallet.

   The cart and product ("shortcut") placements reach PayPal without a shipping address, so the buyer picks
   one inside the wallet. Until now the plugin priced that address through a client-side handler that wrote
   a placeholder address (`Temp`/`Temp`/`Temp`) onto the real order and sent PayPal a single, default
   shipping method. It now declares a server-side callback instead, and PayPal calls it directly.

   A new route, `sylius_paypal_order_shipping_callback` (`POST /paypal/order-shipping-callback`,
   controller `Sylius\PayPalPlugin\Controller\PayPalOrderShippingCallbackAction`), answers with every
   shipping method eligible for the address the buyer chose, each with its own price, or with a `422` naming
   the reason the order cannot be shipped there. Nothing is written to the order: the buyer has approved
   nothing yet.

   It is loaded from `@SyliusPayPalPlugin/config/routes/callback.yaml`, **outside the shop's `/{_locale}`
   prefix** — this is not a page a buyer opens but a request PayPal makes and waits on, and its URL is stored
   with the order at PayPal for that order's lifetime. Under the locale prefix,
   `Sylius\Bundle\ShopBundle\EventListener\NonChannelLocaleListener` answers a locale the channel no longer
   offers with a redirect to the shop homepage, which would hand PayPal HTML where it expects JSON. The
   path stays close to what the other PayPal integrations use for the same endpoint — WooCommerce registers
   `paypal/v1/shipping-callback` on the REST API, Shopware `paypal/express/shipping-callback` on its
   store-api — and keeps PayPal's own `order_update_callback_config` wording in front of it. It is a callback rather than a webhook: the refund webhook is a notification PayPal sends,
   while this one is a synchronous call whose response drives what the wallet renders.

   **This endpoint has to be reachable from PayPal's servers.** It is declared on the order only when the
   generated URL is `https`, so a shop behind a tunnel or on a production domain works, and a local
   development shop simply does not get wallet shipping options. Two knobs matter if the URL comes out wrong:
   `router.request_context.host` and `router.request_context.scheme`.

   That rule lives in `Sylius\PayPalPlugin\Provider\PayPalShippingCallbackUrlProviderInterface`
   (`sylius_paypal.provider.paypal_shipping_callback_url`), which returns `null` rather than a URL PayPal
   could not call. Decorate or replace it if your shop reaches PayPal some other way — for instance behind a
   proxy that terminates TLS in front of an `http` backend.

   Three services carry the work and can be decorated or replaced:
   `Sylius\PayPalPlugin\Resolver\PayPalShippingOptionsResolverInterface` turns an order plus a partial
   address into PayPal's option list, `Sylius\PayPalPlugin\Factory\PayPalShippingAddressFactoryInterface`
   maps PayPal's redacted address onto a Sylius one, and
   `Sylius\PayPalPlugin\Factory\PayPalShippingCallbackResponseFactoryInterface` shapes the answer. The first
   two build on stock Sylius services, so the wallet offers the same methods and prices as the normal
   checkout does for the same address.

   The region is matched **by code, not by name**. PayPal sends it in `admin_area_1`, which is a state or
   province code for the countries it tabulates and the spelling of the region's name for the rest. Sylius
   validates province codes as `XX-YYY` (`/^[A-Z]{2}-[A-Z0-9]{1,}$/`, e.g. `US-FL`), so the factory looks up
   `"{country_code}-{admin_area_1}"` first, then bare `admin_area_1` for shops whose codes bypassed that
   validation. When neither resolves, the value is stored as the address's province *name* instead, so an
   unrecognised region is kept rather than dropped and does not block the order.

   The response factory exists because PayPal validates it: the answer has to carry `amount.breakdown` with
   `shipping` matching the option marked `selected`, and `amount.value` equal to the sum of the breakdown
   (`item_total + tax_total + shipping + handling + insurance - discount - shipping_discount`). If you
   decorate it, keep those two invariants or PayPal rejects the callback.

   The options resolver returns a `Sylius\PayPalPlugin\Model\PayPalShippingOptions` collection of
   `Sylius\PayPalPlugin\Model\PayPalShippingOption` objects rather than arrays, so a decorator adds or
   reprices an option without reproducing PayPal's payload keys. Each option keeps its price in minor units
   and formats it only in `toArray()`; `TYPE_PICKUP` is there for options the buyer collects instead of having
   shipped. The collection answers `isEmpty()` and `selected()`, so nothing downstream has to scan the list to
   find the chosen option.

   Which option is selected when the buyer has not chosen one is decided by
   `Sylius\PayPalPlugin\Factory\PayPalShippingOptionsFactoryInterface`
   (`sylius_paypal.factory.paypal_shipping_options`) — decorate that to change the default, without touching
   the resolver.

   Each option is labelled with the shipping method's name **in the order's locale**, read through
   `getTranslation($order->getLocaleCode())` rather than through the locale Sylius resolves from the request.
   That is what lets the endpoint live outside the shop's `/{_locale}` prefix: a callback PayPal makes from
   its own network carries no buyer locale, and the order records one. A method with no translation for that
   locale keeps the name it was loaded with.

   The buyer's choice is written back by `Sylius\PayPalPlugin\Controller\ProcessPayPalOrderAction`, which
   now also stores the region on the order's addresses — previously it was dropped. The action no longer
   builds those addresses itself: another decoratable service,
   `Sylius\PayPalPlugin\Factory\ExpressOrderAddressFactoryInterface`
   (`sylius_paypal.factory.express_order_address`), turns the approved purchase unit — or, for an order that
   needs no shipping, the customer — into the address the order is given. Decorate that one to change what
   lands on the order after the buyer approves, rather than the controller.

   It no longer replaces addresses the buyer entered in the Sylius checkout. An order that reaches the wallet
   with a shipping address is sent to PayPal as `SET_PROVIDED_ADDRESS`, which the buyer cannot edit there, so
   rebuilding the order's addresses from PayPal's echo could only lose what PayPal does not carry — the
   region, the company, a separate billing address, a phone number typed in the checkout — and leave the
   previous rows behind unreferenced. Such an order now keeps its addresses untouched. Addresses are still
   built from the echo for an order that carried none, which is the case where the buyer picked the address
   in the wallet.

   The region now travels the other way too. Both payloads that carry a shipping address to PayPal — the
   purchase unit built by `Sylius\PayPalPlugin\Model\PayPalPurchaseUnit` and the pre-capture address patch
   in `Sylius\PayPalPlugin\Api\UpdateOrderAddressApi` — send it as `admin_area_1`, which they did not do
   before. The value is the address's province code with the country prefix stripped (`US-TX` on a `US`
   address is sent as `TX`, the spelling PayPal's state and province tables use), or the province name when
   the address carries no code. An address with neither leaves the key out entirely. This is what lets a
   region survive the round trip: PayPal echoes `admin_area_1` back, and
   `Sylius\PayPalPlugin\Factory\PayPalShippingAddressFactoryInterface` resolves it to a province again.

   If your shop overrode `pay_from_cart_page.html.twig` or `pay_from_product_page.html.twig`, drop the
   `updateOrderUrl` and `availableCountries` values from the `stimulus_controller()` call; they are no longer
   read. Leaving them in place is harmless.

1. #### Orders addressed in the PayPal wallet now name their payment source.

   PayPal answers such an order with `PAYER_ACTION_REQUIRED` rather than `CREATED`, and
   `Sylius\PayPalPlugin\Payum\Action\CaptureAction` accepts both. Which statuses count as created comes from
   `Sylius\PayPalPlugin\Provider\PayPalOrderCreatedStatusesProviderInterface`
   (`sylius_paypal.provider.paypal_order_created_statuses`), so decorate that rather than the action if
   PayPal starts answering with something else. If you replaced the action itself, it must accept both, or
   the payment will never receive its `paypal_order_id`.

   ```diff
    final readonly class CaptureAction implements ActionInterface
    {
        public function __construct(
            // ...
   +        private ?PayPalOrderCreatedStatusesProviderInterface $orderCreatedStatusesProvider = null,
        ) {
        }
   ```

   ```diff
    <service id="sylius_paypal.payum.action.capture" class="Sylius\PayPalPlugin\Payum\Action\CaptureAction" public="true">
        <!-- ... -->
   +    <argument type="service" id="sylius_paypal.provider.paypal_order_created_statuses" />
    </service>
   ```

   Not passing it is deprecated and will be prohibited in 3.0; until then the action falls back to the
   default provider, so it keeps accepting both statuses.

1. #### The following routes are deprecated and will be removed in 3.0.

   Both are superseded and have no caller left in the package. They keep working unchanged in 2.1 and only
   emit a deprecation notice.

   | Deprecated route | Replacement |
   |---|---|
   | `sylius_paypal_shop_cancel_last_payment` | none — the payment page no longer reaps abandoned attempts from the browser |
   | `sylius_paypal_shop_update_paypal_order` | `sylius_paypal_order_shipping_callback` |

1. #### The create/capture-order JSON contract is now consistent across the three v6 placements.

   The same value used to be spelled three different ways, and `orderID` meant two different things depending
   on the endpoint — the PayPal order id from the cart placement, the *Sylius* order id from
   `ProcessPayPalOrderAction`. The v6 placements now use one spelling throughout:

   | Endpoint | New keys | Old key, still sent |
   |---|---|---|
   | `CreatePayPalOrderFromCartAction` | `id`, `orderId`, `status` | `orderID` (PayPal order id) |
   | `CreatePayPalOrderFromPaymentPageAction` | `id`, `orderId`, `status` | `order_id` (PayPal order id) |
   | `ProcessPayPalOrderAction` | `syliusOrderId`, `orderId`, `status`\*, plus a new `return_url` | `orderID` (**Sylius** order id) |
   | `CompletePayPalOrderFromPaymentPageAction` | `orderId`, `status` added next to `return_url` | — nothing renamed |

   \* `status` is the payment state, so `ProcessPayPalOrderAction` omits it in the one response it returns when
   the order has no payment left in the cart state — there is no payment to report a state for.

   **This is not a break in 2.1.** Every old key is still sent alongside its replacement, with the same value
   and the same meaning it had in 2.0, so JavaScript reading the old names keeps working. The old keys are
   deprecated and **will be removed in 3.0** — move your code to the new names before then.

   `sylius_paypal_shop_create_paypal_order` follows the same pattern: it gained `orderId` next to the
   `orderID` it has always returned, with the same value and the same meaning.

1. #### Express checkout completes the purchase in the PayPal wallet.

   The PayPal buttons on the cart and product pages used to be a shortcut into the regular checkout: after the
   buyer approved the payment in the wallet, they landed on the Sylius checkout summary and had to press
   "Place order" a second time before anything was captured.

   `Sylius\PayPalPlugin\Controller\ProcessPayPalOrderAction` (route `sylius_paypal_shop_process_paypal_order`)
   now captures the payment and completes the order in the same request, and sends the buyer straight to the
   thank-you page. Its JSON response carries a `return_url` next to the keys described in the entry above.

   Driving the payment and the order to their completed states lives in the new
   `Sylius\PayPalPlugin\Completer\PayPalExpressOrderCompleter` (service `sylius_paypal.completer.express_order`,
   aliased by `Sylius\PayPalPlugin\Completer\PayPalExpressOrderCompleterInterface`), so it can be decorated or
   replaced without touching the controller.

   Two smaller changes come with it: the buyer's phone number from PayPal's `payer` payload is now written onto
   the order's addresses and onto a newly created customer, and a payment amount mismatch no longer leaves the
   request in an error, the payment is detached from the order, the order is reprocessed, and the buyer is
   returned to the checkout summary so the purchase can be retried.

   The action also cross-references the posted `payPalOrderId` against the `paypal_order_id` the plugin itself
   wrote onto the payment's details, and answers `422 Unprocessable Content` when the two disagree — a payment
   carrying no `paypal_order_id` at all included. The response body is unchanged and still carries a
   `return_url` back to the checkout summary, so the buyer is redirected as before. Nothing is fetched from
   PayPal and nothing on the order is touched, which is what separates this from an amount mismatch: that one
   still answers `200`, because the request *is* processed and the payment really is detached.

1. #### The cart and product page button templates no longer receive `completeUrl`.

   `@SyliusPayPalPlugin/pay_from_cart_page.html.twig` and `@SyliusPayPalPlugin/pay_from_product_page.html.twig`
   redirected to a hardcoded checkout summary URL after approval. They now follow the `return_url` returned by
   the process endpoint, the same way `pay_from_payment_page.html.twig` already did, and
   `Sylius\PayPalPlugin\Controller\PayPalButtonsController` no longer passes the `completeUrl` variable to them.

   The redirect itself now lives in the Stimulus controller these two placements render, and that controller
   takes no `completeUrl` value. An override still reading `{{ completeUrl }}` has to be rewritten against the
   new templates anyway, as the Web SDK v6 and Stimulus entries at the top of this file describe.

   Without that the buyer is sent to the checkout summary of an order that is already completed, which is no
   longer a cart, and with `strict_variables` enabled the template fails to render on the undefined variable.

1. #### The following constructor signatures have gained new optional (nullable) arguments.

   Following this package's existing deprecation pattern, not passing them is deprecated and will be
   required in 3.0. If you instantiate, decorate, or redefine any of these services with an explicit
   argument list, add the new argument.

   ```diff
    final readonly class PayWithPayPalFormAction
    {
        public function __construct(
            // ...
   +        private ?PayPalConfigurationProviderInterface $payPalConfigurationProvider = null,
        ) {
        }
   ```

   ```diff
    final class PayPalPayment
    {
        public function __construct(
            // ...
   +        private ?PayPalConfigurationProviderInterface $payPalConfigurationProvider = null,
        ) {
        }
   ```

   ```diff
    final readonly class PayPalButtonsController
    {
        public function __construct(
            // ...
   +        private ?PayPalWebSdkConfigurationProviderInterface $webSdkConfigurationProvider = null,
        ) {
        }
   ```

   Unlike the two above, this one has no usable fallback: the v6 placements cannot be rendered without it,
   so a controller constructed without it throws a `\RuntimeException` when a placement is rendered.

   ```diff
    final readonly class ProcessPayPalOrderAction
    {
        public function __construct(
            // ...
   +        private ?UrlGeneratorInterface $router = null,
   +        private ?PayPalExpressOrderCompleterInterface $orderCompleter = null,
   +        private ?OrderProcessorInterface $orderProcessor = null,
   +        private ?RepositoryInterface $shippingMethodRepository = null,
   +        private ?ExpressOrderAddressFactoryInterface $expressOrderAddressFactory = null,
        ) {
        }
   ```

   ```diff
    <service id="sylius_paypal.controller.process_paypal_order" class="Sylius\PayPalPlugin\Controller\ProcessPayPalOrderAction">
        <!-- ... -->
   +    <argument type="service" id="router" />
   +    <argument type="service" id="sylius_paypal.completer.express_order" />
   +    <argument type="service" id="sylius.order_processing.order_processor" />
   +    <argument type="service" id="sylius.repository.shipping_method" />
   +    <argument type="service" id="sylius_paypal.factory.express_order_address" />
    </service>
   ```

   The first three throw a `\RuntimeException` when they are actually needed — generating a return URL,
   completing the order, or detaching a mismatched payment. The last two degrade instead: the action behaves
   as it did in 2.0, which means the shipping method the buyer chose in the wallet is not applied, and the
   region is not stored because the action falls back to an address factory that resolves none. The first of
   those two fails the amount check and sends the buyer back to the checkout instead of the thank-you page.

   ```diff
    final readonly class CreateOrderApi
    {
        public function __construct(
            // ...
   +        private ?PayPalOrderFactoryInterface $payPalOrderFactory = null,
        ) {
        }
   ```

   ```diff
    <service id="sylius_paypal.api.create_order" class="Sylius\PayPalPlugin\Api\CreateOrderApi">
        <!-- ... -->
   +    <argument type="service" id="sylius_paypal.factory.paypal_order" />
    </service>
   ```

   ```diff
    final readonly class UpdateOrderApi
    {
        public function __construct(
            // ...
   +        private ?PayPalPurchaseUnitFactoryInterface $payPalPurchaseUnitFactory = null,
        ) {
        }
   ```

   ```diff
    <service id="sylius_paypal.api.update_order" class="Sylius\PayPalPlugin\Api\UpdateOrderApi">
        <!-- ... -->
   +    <argument type="service" id="sylius_paypal.factory.paypal_purchase_unit" />
    </service>
   ```

   Both degrade rather than throw: without the factory each API builds the same payload it built in 2.0 from
   the providers it already holds. For `CreateOrderApi` that means an order carrying neither the return and
   cancel URLs nor the shipping callback, so the wallet falls back to its plain flow with no shipping options.

1. #### The PayPal order payload is now assembled by factories.

   `Sylius\PayPalPlugin\Api\CreateOrderApi` and `Sylius\PayPalPlugin\Api\UpdateOrderApi` no longer read the
   order, the gateway config or the router themselves — they ask a factory for the payload and send it. Two
   new services carry that work and can be decorated or replaced:

   | Service | Interface | Builds |
   | --- | --- | --- |
   | `sylius_paypal.factory.paypal_order` | `Sylius\PayPalPlugin\Factory\PayPalOrderFactoryInterface` | the whole `v2/checkout/orders` payload, including the payer return URL and the shipping callback |
   | `sylius_paypal.factory.paypal_purchase_unit` | `Sylius\PayPalPlugin\Factory\PayPalPurchaseUnitFactoryInterface` | one purchase unit, shared by order creation and the `PATCH` that updates it |
   | `sylius_paypal.provider.paypal_shipping_callback_url` | `Sylius\PayPalPlugin\Provider\PayPalShippingCallbackUrlProviderInterface` | the shipping callback URL, or `null` when PayPal could not reach it |
   | `sylius_paypal.factory.paypal_shipping_callback_response` | `Sylius\PayPalPlugin\Factory\PayPalShippingCallbackResponseFactoryInterface` | the body the shipping callback answers with, including the total reconciled against the selected option |

   `PayPalPurchaseUnitFactoryInterface::create()` takes the merchant id as an optional third argument and
   falls back to the `merchant_id` configured on the payment's method, which is what every caller passed
   before. `Sylius\PayPalPlugin\Model\PayPalOrder::INTENT_CAPTURE` now holds the capture intent;
   `CreateOrderApi::PAYPAL_INTENT_CAPTURE` is kept as an alias of it.

   This is where to hook in if you need to change what reaches PayPal — overriding `CreateOrderApi` for that
   is no longer necessary.

1. #### The plugin now caches in its own pool instead of the application's `cache.app`.

   Both places where the plugin caches — the PayPal callback certificates used to verify the shipping
   callback signature, and the cooldown that rate-limits webhook id re-resolution — wrote to `cache.app`,
   mixing the plugin's entries into your shop's own cache. They now go through `sylius_paypal.cache`,
   a private pool parented to `cache.app` and tagged `cache.pool`.

   **No action is required.** The pool inherits whatever adapter you configured in `framework.cache.app`,
   so the plugin follows your Redis, Memcached or filesystem setup as before — it just gets its own
   namespace inside it, and its own `bin/console cache:pool:clear sylius_paypal.cache` target.

   Two consequences worth knowing:

   Existing entries are not migrated. The new namespace starts cold, which costs one extra certificate
     download and resets the webhook id refresh cooldown once.

   Clearing `sylius_paypal.cache` drops both the certificates and the cooldown locks, which allows one
     additional webhook id refresh burst. Clear it per concern only if you split the pool yourself.

   To put the plugin's cache on a different backend than the rest of the application, redefine the service
   in your own configuration:

   ```yaml
   services:
       sylius_paypal.cache:
           parent: cache.adapter.redis
           tags: ['cache.pool']
   ```

1. #### The PayPal payment page now runs on Web SDK v6, inside the shop layout.

   `@SyliusPayPalPlugin/pay_with_paypal.html.twig` was a standalone HTML document that loaded PayPal's JS
   SDK v5 and built a PayPal button and Hosted Fields from inline script. It now extends
   `@SyliusShop/shared/layout/base.html.twig` and renders two funding sources — PayPal and card — through
   PayPal's Web SDK v6. **This was the last JS SDK v5 in the package; no template loads it any more.**

   The two methods are hookables on `sylius_paypal.shop.pay_with_paypal.content`, so a shop can reorder
   them, remove one, or add its own without overriding the page:

   ```yaml
   sylius_twig_hooks:
       hooks:
           'sylius_paypal.shop.pay_with_paypal.content':
               card:
                   enabled: false
   ```

   **If your shop overrode `pay_with_paypal.html.twig`, that override now renders v5 markup with no SDK
   behind it** — no button, no card form, no error. Diff it against the new template and rewrite it, or
   drop the override.

   **Two new Stimulus controllers have to be part of your asset build**, the same way `paypal-web-sdk`
   already is (see the entry near the top of this file). Without them the page renders and nothing
   happens:

   ```json
   "@sylius/paypal-plugin": {
       "paypal-web-sdk": { "enabled": true, "fetch": "lazy" },
       "paypal-payment-wallet-button": { "enabled": true, "fetch": "lazy" },
       "paypal-payment-card-fields": { "enabled": true, "fetch": "lazy" }
   }
   ```

   Then `yarn install && yarn build`.

1. #### Card payments are now refused when 3D Secure does not authorise them.

   The card path used to decide the authentication outcome in the browser and the capture endpoint trusted
   it, so a capture could be requested without passing the challenge. `sylius_paypal_shop_complete_paypal_order`
   now fetches the order from PayPal before capturing and hands the result to
   `Sylius\PayPalPlugin\Verifier\ThreeDSecureVerifierInterface`, which implements PayPal's published
   decision table over `enrollment_status`, `authentication_status` and `liability_shift`.

   A refused authentication cancels the payment — the order stays payable — flashes the reason and returns
   a `return_url` back to the payment page when the buyer can retry, or to the order otherwise. An order
   that carries no `authentication_result` at all, which is every wallet payment and every card that needed
   no challenge, is captured as before.

   Decorate or replace `sylius_paypal.verifier.three_d_secure` to change the policy. The plugin does not
   send `payment_source.card.attributes.verification`, so PayPal's own default applies.

   The endpoint also answers `409` when no payment is being processed, and `422` when the caller names a
   PayPal order the payment does not carry. The identity check is skipped when the request body does not
   name one, so existing callers that post no body are unaffected.

1. #### `sylius_paypal_shop_create_paypal_order` now ends the previous payment attempt.

   The payment page carries two funding sources, so a buyer can start a PayPal attempt, abandon it and then
   submit the card form. Starting an attempt now cancels a PayPal payment left in `processing` before
   creating a new PayPal order, which the payment state machine replaces with a fresh payment in `new`.
   Only a PayPal payment is cancelled, so an order carrying another gateway's processing payment is
   untouched.

   When no payment awaits payment the endpoint answers `409` instead of raising a `TypeError`, and the
   response carries `orderId` next to the existing `orderID`, with the same value.

1. #### `sylius_paypal_shop_create_paypal_order_from_payment_page` now ends the previous payment attempt.

   The checkout payment step leaves the buyer on the page when the wallet window fails or expires, and the
   payment stays in `processing`, so the next click reached an order with no payment in `cart` and raised a
   `TypeError`. Starting an attempt now cancels that payment, re-processes the order so it carries a fresh
   payment in `cart` for the current total, and only then creates the new PayPal order. The payment method
   is preserved, and only a PayPal payment is cancelled, so an order carrying another gateway's processing
   payment is untouched.

   When the order has no payment to pay with, the endpoint answers `409` instead of raising a `TypeError`.

   `CreatePayPalOrderFromPaymentPageAction` gained two nullable arguments — an `OrderProcessorInterface`,
   wired to `sylius.order_processing.order_payment_processor.checkout`, and an `ObjectManager` — which
   together replace the cancelled payment. Not passing them is deprecated and will be prohibited in 3.0;
   without them the endpoint leaves the abandoned attempt untouched and answers `409` rather than raising a
   `TypeError`, so the order keeps a payment that `sylius-paypal:complete-payments` can still reconcile.

1. #### `sylius_paypal_shop_complete_paypal_order_from_payment_page` now leaves the order payable after an amount mismatch.

   When the cart changes while the wallet window is open, the captured amount no longer matches the order
   total. The endpoint cancelled the payment but never persisted what came after, so the order was left
   with nothing to pay with. It now cancels the payment, reprocesses the order and **flushes**, which puts a
   fresh payment in `cart` on the new total back on the order.

   The cancelled payment is no longer detached from the order. `Order::payments` is mapped with orphan
   removal, so detaching it deleted the record of the attempt outright. Keeping it attached also lets
   `OrderPaymentProvider` copy PayPal onto the new payment from the cancelled one, which is what keeps
   PayPal selected on the summary once `sylius_paypal.prioritize_paypal_as_default_method` is turned off
   or `PayPalDefaultPaymentMethodResolver` is gone in 3.0. `Place order` then leads straight back to the
   payment page.

   Two consequences for overridden templates: an order can now carry a cancelled payment next to the new
   one, and the shop summary lists every payment, so both rows are rendered. The buyer also gets an
   `error` flash, `sylius_paypal.order_total_changed`, which is new in `flashes.en.yml`, `flashes.fr.yml`
   and `flashes.nl.yml`.

   The endpoint also answers `409` when the order has no payment in `processing`. It used to read
   `$order->getLastPayment(PaymentInterface::STATE_PROCESSING)` behind a `@var` annotation that claimed it
   was never null and dereferenced it on the next line, so a second submit, a reload or a second tab raised
   an `Error` and answered `500`. `sylius_paypal_shop_complete_paypal_order` already behaved this way.

1. #### `sylius_paypal_shop_payment_error` now releases the attempt the wallet window failed on.

   `onError` was the one wallet callback that told the shop nothing it could act on: the endpoint logged the
   message and flashed `sylius_paypal.something_went_wrong`, and the payment stayed in `processing` until
   the buyer started another attempt. The Stimulus controllers now post `{"error": …, "payPalOrderId": …}`
   as JSON, and the endpoint cancels the payment that PayPal order belongs to and re-processes the order,
   the way `sylius_paypal_shop_cancel_payment` does on cancel — with the error flash instead of the success
   one.

   A payment that cannot take the `cancel` transition is left alone, so a card attempt still in `cart` and
   a payment already completed are untouched, and an unknown PayPal order id is ignored.

   **A request body that is not a JSON object is still read as plain text**, so a template overridden in 2.0
   or 2.1 that posts the raw error string keeps working — it only misses the new cancellation.

   `PayPalPaymentOnErrorAction` gained four nullable arguments — a `PaypalPaymentQueryInterface`, a
   `StateMachineInterface`, an `OrderProcessorInterface` wired to
   `sylius.order_processing.order_payment_processor.checkout`, and an `ObjectManager` — which together
   perform the cancellation. Not passing them is deprecated and will be prohibited in 3.0; without them the
   endpoint only logs and flashes, as it did in 2.0.

1. #### The following signatures changed.

   `PayPalWebSdkConfigurationProviderInterface::getInstanceConfig()` takes the SDK component list and an
   optional locale. Both are optional and default to what the three button placements already send, so
   their configuration is unchanged:

   ```diff
    public function getInstanceConfig(
        ChannelInterface $channel,
        string $pageType,
   +    array $components = self::DEFAULT_COMPONENTS,
   +    ?string $locale = null,
    ): array;
   ```

   `CompletePayPalOrderAction` gained three nullable arguments, which together enable the 3D Secure check.
   Not passing them is deprecated and will be prohibited in 3.0; without them the capture behaves as it did
   in 2.0.

   ```diff
    final readonly class CompletePayPalOrderAction
    {
        public function __construct(
            // ...
   +        private ?CacheAuthorizeClientApiInterface $authorizeClientApi = null,
   +        private ?OrderDetailsApiInterface $orderDetailsApi = null,
   +        private ?ThreeDSecureVerifierInterface $threeDSecureVerifier = null,
        ) {
        }
   ```

   `PayWithPayPalFormAction` gained `?PayPalPaymentPageContextProviderInterface` and a
   `?UrlGeneratorInterface`, both required to render the page. Five of its existing arguments —
   `AvailableCountriesProviderInterface`, `CacheAuthorizeClientApiInterface`, `IdentityApiInterface`,
   `LocaleProcessorInterface` and `PayPalConfigurationProviderInterface` — are no longer used, because the
   page neither mints a Hosted Fields client token nor prices shipping in the browser, and everything it
   renders now comes from the context provider. They became nullable, **passing them is deprecated** and
   they will be removed in 3.0. Its service definition uses named arguments, so dropping them does not
   shift the remaining positions.

   `Sylius\PayPalPlugin\Provider\PayPalPaymentPageContextProviderInterface`
   (`sylius_paypal.provider.paypal_payment_page_context`) builds everything the page renders: the URLs it
   calls, the v6 instance configuration including the `card-fields` component, and the order being paid
   for. Decorate or replace it to change what the page receives without replacing the controller.

1. #### The created PayPal order now carries full line items, an amount breakdown, `custom_id`/`invoice_id`,
   and an enriched `experience_context`.

   Following the PayPal SDD, the `v2/checkout/orders` payload - now assembled by `PayPalOrderFactory` and
   `PayPalPurchaseUnitFactory` (see above) - changed shape:

   **The order sends an enriched `payment_source.paypal.experience_context` in place of the deprecated
     `application_context`, on every flow.** It carries `locale`, `shipping_preference`, `contact_preference`,
     `user_action`, `payment_method_preference`, an `app_switch_preference` and identical `return_url`/`cancel_url`
     (the absolute payer return URL - they must be identical for app switch to work). The `shipping_preference`
     follows the flow: shortcut placements with no known address yield `GET_FROM_FILE` + `UPDATE_CONTACT_INFO`,
     a known shipping address yields `SET_PROVIDED_ADDRESS` + `RETAIN_CONTACT_INFO`, and a non-shippable order
     yields `NO_SHIPPING`. `order_update_callback_config` is added only on the `GET_FROM_FILE` flow, when PayPal
     can reach the callback URL - PayPal rejects it (`SHIPPING_CALLBACK_CONFIG_NOT_SUPPORTED`) together with
     `SET_PROVIDED_ADDRESS` or `NO_SHIPPING`. The choice no longer depends on the shipping preference - the v6
     card fields introduced in this release work against an order that carries `experience_context`, so
     `application_context` is not sent any more.
   
   **`brand_name` is not sent by default.** PayPal then shows the business name registered on the merchant's
     account. The whole `experience_context` is built by
     `Sylius\PayPalPlugin\Provider\ExperienceContextProviderInterface`
     (`sylius_paypal.provider.experience_context`); decorate it to add a `brand_name` or override any other key
     without reimplementing order creation.
   
   **Each `purchase_units[].items[]` entry now includes `category`, and, when resolvable, `sku` (variant
     code), `description` (product short description) and `url` (product page).** `category` is
     `DIGITAL_GOODS` for orders that do not require shipping and `PHYSICAL_GOODS` otherwise. An
     integration using the partner (platform) fee functionality must keep `PHYSICAL_GOODS` for digital goods;
     this package does not use partner fees, so digital orders are marked `DIGITAL_GOODS`.
   
   **`custom_id` and a per-attempt-unique `invoice_id`.** `custom_id` carries the stable payment reference
     number so PayPal notifications can be resolved back to the Sylius payment; `invoice_id` appends the
     per-attempt reference id to that number so a retried payment never collides on the value PayPal rejects
     when duplicated.

1. #### `PayPalItemDataProvider` gained an optional `router` argument.

   `Sylius\PayPalPlugin\Provider\PayPalItemDataProvider` now takes a
   `Symfony\Component\Routing\Generator\UrlGeneratorInterface` (the `router` service) as an optional last
   constructor argument, used to build the item product URLs. Omitting it is deprecated and the provider then
   skips the `url` field; if you instantiate or decorate the provider yourself, pass the `router` service.

1. #### `PayPalPurchaseUnit` and `PayPalOrder` model constructors changed.

   `Sylius\PayPalPlugin\Model\PayPalPurchaseUnit` gained a trailing optional `?string $customId = null`
   argument; existing positional calls keep working.

   `Sylius\PayPalPlugin\Model\PayPalOrder` keeps its existing `$order`, `$payPalPurchaseUnit` and `$intent`
   arguments and gains a trailing optional `array $experienceContext = []` one; existing positional calls
   keep working, although `$order` is now unused - it is deprecated and will be removed in 3.0. The model no
   longer assembles the experience context itself - it only carries the one it is given - so its `toArray()`
   always sends that array under `payment_source.paypal.experience_context`, never the legacy
   `application_context`. Build the context with
   `Sylius\PayPalPlugin\Provider\ExperienceContextProviderInterface`
   (`sylius_paypal.provider.experience_context`) the way `PayPalOrderFactory` does; constructing a
   `PayPalOrder` without `$experienceContext` now sends an empty experience context.

19. #### Pay Later has a real button, and `<paypal-message>` finally renders real content.

   The Pay Later payment method now has its own v6 button (`createPayLaterOneTimePaymentSession`), shown on
   the product, cart, and checkout payment-page placements whenever `findEligibleMethods()` says the buyer
   is eligible. Two new, opt-out admin toggles on the PayPal payment method's gateway config control this:

   - `pay_later_enabled` — hides the Pay Later button when off.
   - `messaging_enabled` — hides the `<paypal-message>` financing message (see below) when off.

   Both default to `true`, including for existing (pre-2.1) payment methods, whose stored config simply
   won't have these keys yet — these are merchant opt-outs, not opt-ins.

   `<paypal-message>` (e.g. "Pay in 4 interest-free payments of $X") now renders real content on the
   product, cart, and checkout pages, and its "Learn more" link opens a populated modal with the real
   installment breakdown — both are genuinely new. PayPal's own documentation never describes what's
   actually required to make either one fetch content, so this was root-caused by reading the shipped
   `web-sdk/v6/core` bundle's own component source directly. Two non-obvious requirements, in case you
   maintain a custom messaging placement of your own:

   - The element's `amount` must be a string with up to two decimal places (e.g. `"29.41"` or `"29.4"`), not
     a JavaScript number or an unformatted division result — the component's own content-delivery round-trip
     silently drops a numeric amount, and PayPal's own SDK validation warns when the string has more than two
     decimal places.
   - The SDK instance must be created (`createInstance()`, with `paypal-messages` in its `components`)
     *before* awaiting `customElements.whenDefined('paypal-message')` — the component itself, along with
     `createPayPalMessages()`, `fetchContent()`, and `getFetchContentOptions()`, is only defined as a side
     effect of that call; none of it exists in the base `web-sdk/v6/core` bundle. Waiting on the definition
     first only happens to work when another controller on the same page creates an instance with
     `paypal-messages` first — remove or move that other placement and the wait never resolves.

   `<paypal-message>` ships as its own new Stimulus controller,
   `data-controller="sylius--paypal-plugin--paypal-message"`, alongside the existing `paypal-web-sdk` one —
   it needs the same one-time registration described above, or it renders nothing and errors silently just
   like an unregistered `paypal-web-sdk` would:

   ```json
   "@sylius/paypal-plugin": {
       "paypal-web-sdk": { "enabled": true, "fetch": "lazy" },
       "paypal-message": { "enabled": true, "fetch": "lazy" }
   }
   ```

   `Sylius\PayPalPlugin\Twig\PayPalExtension` gained three new nullable constructor arguments for this,
   following the same deprecation pattern as the rest of this document:

   ```diff
    final class PayPalExtension extends AbstractExtension
    {
        public function __construct(
            private readonly bool $sandbox,
   +        private readonly ?PayPalFundingSourcesConfigurationProviderInterface $fundingSourcesConfigurationProvider = null,
   +        private readonly ?ChannelContextInterface $channelContext = null,
   +        private readonly ?PayPalWebSdkConfigurationProviderInterface $webSdkConfigurationProvider = null,
        ) {
        }
   ```

   Without them, `sylius_paypal_is_messaging_enabled()` returns `false`, and
   `sylius_paypal_web_sdk_script_url()`/`sylius_paypal_web_sdk_instance_config()` return an empty
   string/array — the messaging placement degrades to rendering nothing rather than erroring.
