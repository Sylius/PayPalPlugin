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

1. #### Constructor changes on `CreatePayPalOrderFromCartAction`, `CreatePayPalOrderFromPaymentPageAction`, `CompletePayPalOrderFromPaymentPageAction`, `ProcessPayPalOrderAction` and `AddToCartAction`.

   The first four each gained an optional, appended `Sylius\PayPalPlugin\Verifier\OrderOwnershipVerifierInterface`
   argument, deprecated when absent like every other constructor addition in this document — except a missing
   instance throws a `\RuntimeException` on first use instead of only a deprecation notice. If you've redefined
   any of these services with an explicit argument list, add `sylius_paypal.verifier.order_ownership` to it
   before 3.0.

   `AddToCartAction` gained an optional, appended `Sylius\Component\Core\Storage\CartStorageInterface`
   argument; a missing instance only triggers a deprecation notice here, no exception.

   `Sylius\PayPalPlugin\Exception\OrderNotFoundException` now extends `NotFoundHttpException` instead of
   implementing `HttpExceptionInterface` directly.

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

   Card orders send `payment_source.card.attributes.verification.method` as `SCA_WHEN_REQUIRED`, hardcoded,
   with no back-office toggle — 3DS then fires wherever regulation or the card network requires it and
   nowhere else, matching the SDD's own choice over `SCA_ALWAYS` (a challenge for every card payment,
   including buyers nobody needed to challenge). `PaymentPageCardFieldsController` names `card` when it
   starts an attempt, instead of leaving the request to fall back to `paypal` silently — without this,
   PayPal never runs 3DS at all, so the decision table above never has a result to act on.

   **Shops upgrading will start seeing real 3D Secure challenges on card payments where they saw none
   before** — this is a conversion-visible behavior change, not just an internal correctness fix. Decorate
   or replace `sylius_paypal.verifier.three_d_secure` to change the policy.

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
   arguments and gains a required trailing `array $paymentSource` one, although `$order` is now unused - it
   is deprecated and will be removed in 3.0. The model no longer assembles the payment source itself - it
   only carries the one it is given - so its `toArray()` always sends that array as `payment_source`, never
   the legacy `application_context`. Build the payment source with
   `Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface`
   (`sylius_paypal.provider.paypal_payment_source`) the way `PayPalOrderFactory` does; it wraps the
   experience context built by `Sylius\PayPalPlugin\Provider\ExperienceContextProviderInterface`
   (`sylius_paypal.provider.experience_context`) under the `paypal` key.

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

1. #### The PayPal order now names the payment source the buyer chose.

   Every order the plugin created carried `payment_source.paypal`, whichever button started it.
   `Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface`
   (`sylius_paypal.provider.paypal_payment_source`) now builds that node per method, and the buyer's choice
   travels with the order from the button to the API call. Decorate it to teach the plugin a method of your
   own.

   The two factory signatures gained trailing optional arguments — the payment source, and the payer action
   nonces the redirect routes are answered with:

   ```diff
    // Sylius\PayPalPlugin\Api\CreateOrderApiInterface
    public function create(
        string $token,
        PaymentInterface $payment,
        string $referenceId,
   +    string $paymentSource = PayPalPaymentSourceProviderInterface::PAYPAL,
   +    ?string $payerActionReturnNonce = null,
   +    ?string $payerActionCancelNonce = null,
    ): array;

    // Sylius\PayPalPlugin\Factory\PayPalOrderFactoryInterface
    public function create(
        PaymentInterface $payment,
        string $referenceId,
   +    string $paymentSource = PayPalPaymentSourceProviderInterface::PAYPAL,
   +    ?string $payerActionReturnNonce = null,
   +    ?string $payerActionCancelNonce = null,
    ): PayPalOrder;
   ```

   Existing **calls** keep working, positional ones included. An existing **implementation** of
   `CreateOrderApiInterface` does not: PHP requires it to declare every parameter the interface declares, so
   a class still carrying the three-argument signature is a fatal error rather than a deprecation. Add both
   arguments to it. `PayPalOrderFactoryInterface` is new in 2.1 and has no released signature to preserve.
   A factory that builds a redirect order without a nonce now throws, because the URLs it would hand PayPal
   could not be told apart from anyone else's.

   `CreatePayPalOrderAction` gained a nullable `?PayPalPaymentSourceProviderInterface`. Not passing it is
   deprecated and will be prohibited in 3.0; without it the endpoint accepts `paypal` and nothing else.

   `PayPalPaymentPageContextProvider` gained a **required** `PayPalFundingSourcesConfigurationProviderInterface`,
   because it decides which SDK components the page asks for. That class is new in 2.1 and has no released
   signature to preserve; if you build it yourself, pass `sylius_paypal.provider.paypal_configuration`.

   `PayPalFundingSourcesConfigurationProviderInterface` gained `isGooglePayEnabled(ChannelInterface $channel)`.
   Implement it if you implement that interface from scratch rather than decorating the shipped provider.

   `sylius_paypal_shop_create_paypal_order` now reads an optional JSON body naming the source:

   ```json
   { "paymentSource": "google_pay" }
   ```

   A body that is absent, empty or not valid JSON still means `paypal`, so callers that post nothing behave
   exactly as before. A source the provider does not recognise is answered with `422 Unprocessable Entity`,
   and no payment is created or cancelled.

   The chosen source is then stored as `payment_source` in `Payment::getDetails()` and carried through
   capture and completion, so anything reading those details should expect the extra key.

1. #### Google Pay is available on the PayPal payment page.

   A new tile on `/pay-with-paypal/{orderToken}/{paymentId}`, between the PayPal wallet and the card fields.
   One new, **opt-in** admin toggle on the PayPal payment method's gateway config controls it:

   - `google_pay_enabled` — defaults to `false`, including for existing payment methods.

   This is the opposite default from `pay_later_enabled` and `messaging_enabled`, which are opt-outs. Pay
   Later was already running in production when its toggle arrived; Google Pay has never run, the merchant
   has to enable the Google Pay capability on their PayPal account for it to work at all, and SDD §4.1.4
   asks for the methods a merchant has opted into. Turning it on in the back office is the same decision
   they already have to make on PayPal's side.

   Google Pay is not a PayPal web component. The button is drawn by **Google's own SDK**, which the page
   loads from `https://pay.google.com/gp/p/js/pay.js` — a second third-party script alongside PayPal's.

   **If your shop sends a Content-Security-Policy**, allow `pay.google.com` in `script-src`, and
   `pay.google.com google.com account.google.com www.google.com` in `connect-src`, which is what Google
   documents for its API. Otherwise the tile silently renders nothing. A strict CSP built on nonces needs
   more than that: Google injects its own `<style>` and `<script>` elements, including the button's styles,
   and expects the nonce both on the `pay.js` tag and on `PaymentsClient`. The plugin does not pass one,
   because Sylius has no nonce for it to read; a shop on a nonce-based CSP has to override the tile
   template and the controller.

   It ships as its own Stimulus controller and needs the same one-time registration as the others:

   ```json
   "@sylius/paypal-plugin": {
       "paypal-payment-google-pay": { "enabled": true, "fetch": "lazy" }
   }
   ```

   Orders created for Google Pay carry `payment_source.google_pay.attributes.verification.method` set to
   `SCA_WHEN_REQUIRED`, so 3D Secure runs where regulation or the card network demands it.
   `ThreeDSecureVerifier` now finds `authentication_result` under any payment source, directly or nested
   under `card`, because a wallet nests it one level deeper than a card does. Card payments are unaffected.

   **Known limitation.** If PayPal answers `confirmOrder()` with `PAYER_ACTION_REQUIRED`, the payment is
   failed rather than captured. PayPal documents the branch where that status is absent and not the one
   where it is present, and capturing a payment the buyer has not finished authorising is not a guess worth
   making. Reachable in the EEA; to be revisited once PayPal documents it.

1. #### The payment page now tells the payer that PayPal processes their data.

   SDD §4.1.4 requires the payer to be told, by one of two routes: the prescribed sentence plus a link to
   PayPal's privacy notice at checkout, or a longer prescribed paragraph in the store's own privacy notice
   shown before payment. The plugin ships the first, so a shop is compliant without editing anything. The
   wording is PayPal's and is deliberately not paraphrased; only the English translation key is filled in,
   and `fr`/`nl` fall back to it rather than carry a translation nobody has approved.

   If you take the privacy-notice route instead, drop the hook:

   ```yaml
   sylius_twig_hooks:
       hooks:
           'sylius_paypal.shop.pay_with_paypal.content':
               privacy_notice:
                   enabled: false
   ```

   The priorities on that hook were renumbered to fit the two new templates in — `flashes` 400, `paypal`
   300, `google_pay` 200, `card` 100, `privacy_notice` 0. If you added a tile of your own with an explicit
   priority, check where it now lands.

1. #### PayPal Package Tracking: shipping an order now sends tracking to PayPal (server-side, opt-in per shipment).

   When a shipment transitions to *shipped*, the plugin calls PayPal's Add Tracking API
   (`POST /v2/checkout/orders/{id}/track`) for that parcel, using the order and capture identifiers already
   stored in the payment details and the items belonging to that shipment. PayPal then tracks the parcel
   onward from the carrier network - no ongoing status updates are pushed from Sylius. This builds on the
   enriched order payload above: the tracking items are matched to the order items by the same `sku`
   (`ProductVariant::getCode()`).

   **Namespace.** Everything specific to this feature lives under `Sylius\PayPalPlugin\PackageTracking\`
   (`src/PackageTracking/`), with the usual type sub-namespaces inside it (`Entity`, `Processor`, `Provider`, ...).
   Its entity is mapped by the plugin itself, so no Doctrine mapping configuration is needed on the app side.

   **New database table.** A plugin-owned table `sylius_paypal_plugin_shipment_tracking` (keyed by shipment,
   holding the carrier, PayPal tracker id and sync state) is added - **no change to the core `Shipment`
   entity**. Run migrations:

   ```bash
   bin/console doctrine:migrations:migrate
   ```

   **Admin.** For orders paid with PayPal, the shipment ship form gains a carrier selector (with an `OTHER`
   fallback that reveals a free-text carrier name). A carrier is required whenever a tracking number is entered.
   Each shipment on the order page shows its PayPal sync state (pending / synced / failed). Orders paid with any
   other method keep the stock Sylius ship form, untouched.

   The selector is added as an unmapped `paypal_tracking` sub-form (`ShipmentTrackingType`, backed by the
   `ShipmentTrackingData` model), and only for shipments whose order was paid with PayPal - so a template
   overriding `@SyliusPayPalPlugin/admin/shipment/component/ship.html.twig` reaches the fields as
   `form.paypal_tracking.carrier` and `form.paypal_tracking.carrier_name_other`. Both rules above are enforced by
   the `ShipmentTrackingCarrier` constraint (`config/validation/ShipmentTrackingData.xml`, validation group
   `sylius`), so they surface as regular field-level validation messages and the ship transition is not applied.

   **Configuring the carriers.** The selector is driven by `sylius_paypal.tracking.carriers`, which defaults to a
   curated subset of the codes accepted by the [PayPal Add Tracking API](https://developer.paypal.com/docs/tracking/reference/carriers/).
   Listing your own codes **replaces** that default, so a shop using three carriers ends up with a three-entry
   selector rather than the full list:

   ```yaml
   # config/packages/sylius_paypal.yaml
   sylius_paypal:
       tracking:
           carriers:
               - INPOST_PACZKOMATY
               - DPD_POLAND
               - POCZTA_POLSKA
   ```

   `OTHER` is always appended, so the free-text fallback cannot be configured away. Labels come from the
   `sylius_paypal.carrier.<CODE>` translation keys; a code without a translation falls back to the raw code, so
   adding a carrier outside the curated list only requires a translation entry to make it pretty.

   **Failure isolation.** Sending tracking is a courtesy to PayPal, not a precondition for shipping: the call
   never blocks or reverts shipping. The guarantee lives in `ShipmentTrackingDispatcher`, which logs anything
   thrown while dispatching to the `paypal` channel instead of letting it reach the ship transition.

   **Permanent vs. transient failures.** A failure PayPal will never accept on a retry - an order status that is
   not eligible for tracking, a PayPal order without items, a missing tracking number or carrier, an unresolvable capture id - is recorded on
   the tracking record (state `failed`, with the error) and not retried. Anything else (HTTP errors, timeouts,
   PayPal 5xx) is recorded *and rethrown*, so that when the message is routed to an async transport Messenger's
   own retry strategy can take over. Either way the record can be retried by hand with:

   ```bash
   bin/console sylius-paypal:send-shipment-tracking
   ```

   The command processes every pending or failed record in one run, in batches (`--batch-size`, default `100`),
   clearing the entity manager between batches to keep memory flat.

   Note that a shipment which is not in the `shipped` state yet raises `ShipmentTrackingNotReadyException`
   instead of being marked as failed. Under an async transport the message can reach a worker before the
   shipping request commits its transaction, and without this the worker would read the pre-transition row and
   fail permanently with "Shipment has no tracking number"; raising instead lets the retry pick it up once the
   commit has landed.

   **Optional async.** The call is dispatched as the `Sylius\PayPalPlugin\PackageTracking\Message\SendShipmentTracking`
   message. With no messenger routing configured it is handled synchronously; route it to an async transport
   for full off-request processing:

   ```yaml
   framework:
       messenger:
           routing:
               'Sylius\PayPalPlugin\PackageTracking\Message\SendShipmentTracking': async
   ```

1. #### Trustly is available on the PayPal payment page.

   A new tile on `/pay-with-paypal/{orderToken}/{paymentId}`, between Google Pay and the card fields. One
   new, **opt-in** toggle on the PayPal payment method's gateway config controls it:

   - `trustly_enabled` — defaults to `false`, including for existing payment methods.

   Same default as `google_pay_enabled` and for the same reason: the merchant has to onboard the `TRUSTLY`
   capability on their PayPal account before it works at all
   (`https://www.paypal.com/bizsignup/add-product?product=trustly&capabilities=TRUSTLY&country.x=<cc>`;
   sandbox uses `/bizsignup/entry` with the same query), and SDD §4.1.4 asks for the methods a merchant has
   opted into.

   **Trustly does not exist in Web SDK v6.** `findEligibleMethods()` answers about `card`, `paypal`,
   `venmo`, `paylater`, `credit`, `advanced_cards`, `applepay` and `googlepay` — no alternative payment
   method — and there is no Trustly payment session to create. PayPal's own Trustly guide is still written
   against JS SDK v5. So the tile is server-side: the order is created through the endpoint the wallets
   already use, and the buyer is sent to PayPal with a full-page redirect. Nothing about it touches the v6
   instance, which also means it keeps working on a shop whose PayPal app lacks the v6 feature.

   Reach: Austria, Germany, Denmark, Estonia, Spain, Finland, Great Britain, Lithuania, Latvia, the
   Netherlands, Norway and Sweden, in `EUR`, `DKK`, `SEK`, `GBP` or `NOK`. No vaulting, no chargebacks, no
   shipping callback; refunds work for up to 365 days.

   It ships as its own Stimulus controller and needs the same one-time registration as the others:

   ```json
   "@sylius/paypal-plugin": {
       "paypal-payment-redirect-button": { "enabled": true, "fetch": "lazy" }
   }
   ```

   The tile sits at hook priority `150`, between `google_pay` (200) and `card` (100). **No existing priority
   changed.**

   **Settlement is asynchronous, and this is the part to read twice.** PayPal captures the order itself, on
   approval, because the order is created with
   `processing_instruction: ORDER_COMPLETE_ON_PAYMENT_APPROVAL`. The moment the buyer approves at their
   bank the PayPal *order* reports `COMPLETED` — while the capture underneath is still `PENDING`, for up to
   seven days. **`order.status === 'COMPLETED'` is not proof of payment for a redirect method.** Only
   `purchase_units[0].payments.captures[0].status` is. The Sylius payment stays `processing`, and the order
   stays `awaiting_payment`, until the capture completes. **Do not ship on a `processing` payment.**

1. #### `sylius-paypal:complete-payments` now completes on the capture status, not the order status.

   The command used to complete any `processing` PayPal payment whose PayPal **order** reported `COMPLETED`.
   With a redirect method that is true while the money is still in transit, so the cron would have marked
   unpaid orders as paid and a later `PAYMENT.CAPTURE.DENIED` would have found a `completed` payment it
   could not fail.

   It now delegates to `Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface`
   (`sylius_paypal.processor.payment_settlement`), which reads the capture. For the wallet and card flows
   the outcome is identical — `CompleteOrderAction` produces a completed capture alongside a completed
   order. It differs only for an order reporting `COMPLETED` with no capture recorded, which is now left
   alone instead of being completed.

   The command's constructor keeps its released signature. The four arguments the new code does not read
   directly — the object manager, the authorize and order-details APIs and the state machine — are now
   optional and deprecated, and the settlement processor is appended as a sixth. Passing any of the
   deprecated four triggers a deprecation; they will be removed in 3.0.

   A command built the way 2.0 documented still settles. Given those four and no settlement processor, it
   assembles one itself with a null logger, so nothing silently stops running. Only a command built with the
   repository alone — a signature no release ever offered — reports a failure and settles nothing.

1. #### The webhook endpoint now dispatches by event type.

   Same route name, same path (`POST /paypal-webhook/api/`) — PayPal registers one URL per webhook and the
   id is looked up by that URL, so everything has to land there.
   `Sylius\PayPalPlugin\Controller\Webhook\PayPalWebhookAction` verifies the request once and hands the
   payload to whichever `Sylius\PayPalPlugin\Processor\Webhook\WebhookProcessorInterface` claims the event.

   `RefundOrderAction` and its service id keep working and are deprecated in favour of the dispatcher plus
   `RefundOrderWebhookProcessor`. The verification block it used to inline now lives in
   `Sylius\PayPalPlugin\Verifier\PayPalWebhookRequestVerifierInterface`.

   The plugin now subscribes to `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED`,
   `PAYMENT.CAPTURE.DECLINED` and `PAYMENT.CAPTURE.PENDING` alongside the existing
   `PAYMENT.CAPTURE.REFUNDED`. (`DENIED` and `DECLINED` are both subscribed because PayPal's Trustly guide
   names the first and the SDD's webhook table names the second.)

   **Existing shops have to run one command:**

   ```
   bin/console sylius-paypal:register-webhook-event-types
   ```

   A webhook registered before this release is subscribed to `PAYMENT.CAPTURE.REFUNDED` only, and
   re-enabling the payment method does not update it — PayPal answers `WEBHOOK_URL_ALREADY_EXISTS` and the
   registrar gives up. The command `PATCH`es the existing webhook, so its id — and with it the id stored on
   the gateway config and every in-flight signature check — stays valid. A shop that never got a webhook
   registered at all — because the URL was not public when the payment method was enabled — gets one
   created instead.

   **Set `sylius_paypal.webhook_base_url` before running it from the CLI.** There is no request to build an
   absolute URL from, so the router falls back to `http://localhost/…`, which PayPal will not accept and
   which matches no registered webhook. The same parameter is what makes the plugin agree with itself about
   the webhook URL: registering, looking the id up and verifying a signature all go through
   `Sylius\PayPalPlugin\Provider\PayPalWebhookUrlProviderInterface` now, where registration previously
   ignored the parameter and used the request context instead.

   **A shop that never runs it is not broken, only slower.** A Trustly payment still settles through the
   return page and through `sylius-paypal:complete-payments`.

   **The endpoint now answers `503` when it could not finish an event.** It used to answer `204` whatever
   happened, so a timeout talking to PayPal, a database error or a refused transition lost the event for
   good. A failure the plugin recognises as permanent — the refund document PayPal sent carries no link back
   to the capture — is still logged and answered `204`, because no replay could fix it. Anything else is
   logged at `critical` and answered `503`, which is
   PayPal's signal to deliver the event again; it retries for about three days. The cost is that a genuine
   outage now produces days of retries instead of silence, and that is the trade made deliberately here: a
   visible failure beats an unnoticed lost event about money.

   A processor marks its own failures permanent by throwing an exception implementing
   `Sylius\PayPalPlugin\Exception\PermanentWebhookFailureInterface`; only `PayPalWrongDataException` does,
   and every place that throws it is the refund webhook. `PayPalPaymentMethodNotFoundException` deliberately
   does not — it looks permanent, but an operator can fix it, and PayPal's retry window is the window to fix
   it in. Neither does `PaymentNotFoundException`, which a repository query throws and which the shipping
   callback and the JavaScript error endpoint both catch: a payment PayPal knows about may simply not be
   committed on this side yet, and both shipped processors catch it themselves anyway.

   A replay re-runs every processor that claims the event, so `WebhookProcessorInterface::process()` is now
   documented as having to be idempotent. Both shipped processors are: settlement re-reads the capture
   status from PayPal and guards the transition, and a replayed refund finds the payment already `refunded`.
   Check your own processors before this release reaches production.

   Two changes reach shops that never enable Trustly. An event for an order the shop does not have now
   answers `204` instead of `404`, so PayPal stops retrying it. And once `PAYMENT.CAPTURE.COMPLETED` is
   subscribed, **every** card and wallet payment delivers one too; the handler finds those payments already
   completed and does nothing, but it is traffic that did not exist before.

1. #### A payment waiting at the buyer's bank is protected from being cancelled.

   While a redirect payment is `processing` and carries a `payer_action_url`, `PaymentStateManager::cancel()`
   is a no-op. Six paths reap a live `processing` attempt — creating a new order, the cancel endpoints, the
   JavaScript error reporter — and any of them cancelling a payment whose money is already in flight would
   let the buyer pay twice.

   Consequences you may notice:

   - `POST /create-pay-pal-order/{token}` answers `409 Conflict` while such a payment exists, rather than
     cancelling it and starting a new attempt.
   - `/pay-with-paypal/{orderToken}/{paymentId}` redirects to the order page instead of rendering the
     method tiles.
   - The thank-you page hides its "Change payment method" button, and the plugin's different-amount notice
     is suppressed, for such a payment. A new notice on the order page says the transfer is on its way and
     offers the link back to the bank.

   `sylius_paypal_shop_cancel_payment` and `sylius_paypal_shop_cancel_last_payment` are deliberately **not**
   guarded: they are the buyer's deliberate way out of an attempt they abandoned at the bank.

   The predicate is `Sylius\PayPalPlugin\Checker\PayerActionCheckerInterface`
   (`sylius_paypal.checker.payer_action`), also exposed to templates as
   `sylius_paypal_is_awaiting_payer_action(payment)`.

1. #### Three new shop routes, and a changed `return_url`.

   ```
   GET /{_locale}/paypal/redirect-return/{token}/{nonce}    sylius_paypal_shop_redirect_return
   GET /{_locale}/paypal/redirect-cancel/{token}/{nonce}    sylius_paypal_shop_redirect_cancel
   ```

   Both live under the shop's `/{_locale}` prefix, unlike `sylius_paypal_order_shipping_callback`. That
   endpoint is a request PayPal's servers make and wait on; these are browser navigations by the buyer that
   answer with a redirect to a shop page, so the locale prefix is right for them. They identify the payment
   by the order token in the path and never by the session, which does not survive every browser's
   `SameSite` policy across an off-site redirect.

   The order token alone would let anyone holding it cancel a transfer that is already on its way, because
   the cancel route reaches the payment state machine directly and so bypasses the `payer_action_url` guard
   above. Each attempt therefore mints two nonces — `payer_action_return_nonce` and
   `payer_action_cancel_nonce` — stores them in the payment details next to `payer_action_url` and puts one
   in each of the URLs PayPal is given; a request whose nonce does not match its own route answers `404`.
   One per route rather than one per attempt, so a value that leaks from the return URL cannot be used to
   cancel a transfer that is on its way. Each is per attempt rather than per request — PayPal may send the
   buyer back more than once — and a new attempt replaces both. Settling the payment drops all three keys
   from the details, so none of them outlives the attempt that produced it. They come from
   `Sylius\PayPalPlugin\Provider\NonceProviderInterface` (`sylius_paypal.provider.nonce`), and
   `PayerActionCheckerInterface` gained `matchesPayerActionReturnNonce()` and
   `matchesPayerActionCancelNonce()` to compare them.

   The cancel route answers four ways. A payment PayPal has completed after all goes to the thank-you page;
   one the bank refused says so with `sylius_paypal.something_went_wrong`, as the return route does; a
   cancellation that actually happened says so with `sylius_paypal.payment_cancelled`; and an order with
   nothing in flight is redirected with no message at all. Earlier builds of this branch announced a
   cancellation on all four.

   Orders created for a redirect method now point `return_url` and `cancel_url` at those two routes instead
   of both at `sylius_shop_checkout_complete`. Wallet and card orders are unchanged.

1. #### Amounts PayPal is told about are formatted for the currency.

   PayPal refuses a decimal amount in HUF, JPY and TWD — "This currency does not support decimals. If you
   pass a decimal amount, an error occurs" — and the plugin formatted every `value` field with two decimal
   places whatever the currency. `Sylius\PayPalPlugin\AmountUtils` now decides:
   `toPayPalValue(int $amount, string $currencyCode)` formats an amount for PayPal, and
   `toMinorUnits(string $value)` reads one back.

   `ext-intl` is not the authority for this. ISO 4217 gives HUF two decimal places and PayPal gives it none,
   so the list of currencies PayPal takes whole is a constant on the helper rather than something derived
   from `NumberFormatter`.

   Only the two places this release added use the helper: `FindEligibleMethodsApi`, whose eligibility call a
   Japanese channel would have had rejected outright, and `PayPalCapture`. The other places that build a
   `value` are unchanged and still assume two decimals; moving them is a separate change.

   Reading an amount back needs no currency, because Sylius stores every amount as hundredths whatever the
   currency: a JPY capture of `"1000"` is 100000 either way. The corollary is worth knowing — in those three
   currencies a Sylius amount that is not a round hundred cannot be expressed to PayPal at all, so it is
   rounded on the way out and the capture that comes back will not match. Settlement still completes the
   payment and records `captured_amount` and `captured_currency_code` on it.

1. #### Smaller changes worth knowing about.

   - `PayPalPaymentSourceProviderInterface` gained `TRUSTLY` and `BASE_EXPERIENCE_CONTEXT_KEYS`. The
     Trustly node carries `name`, `country_code` and `email` from the order's billing address and customer,
     and an `experience_context` trimmed to the five keys PayPal's `experience_context_base` accepts —
     `user_action`, `contact_preference`, `payment_method_preference` and `app_switch_preference` are
     `paypal`-only and are dropped.
   - `PayPalOrder` gained a trailing optional `?string $processingInstruction = null`, and `toArray()` may
     now emit `processing_instruction`.
   - `CaptureAction` now keeps the `payer-action` link from the create-order response as
     `payer_action_url` in the payment details, alongside the two payer action nonces of the attempt. Only a
     redirect payment source gets either; a wallet or card order is unchanged. The action gained a trailing
     optional `?NonceProviderInterface`, and not passing it is deprecated.
   - `CompleteOrderAction` returns early for a redirect payment source. PayPal has already captured such an
     order, and patching or capturing it again would fail — invisibly, because the client swallows non-2xx
     responses.
   - `Sylius\PayPalPlugin\Repository\Query\SettleablePaypalPaymentQueryInterface` is new, carrying
     `getForSettlementByOrderId(): PaymentInterface` — it throws `PaymentNotFoundException` rather than
     answering `null`, so callers do not need to check — and backed by a new
     `sylius_paypal.repository.query.pay_pal_payment.settleable_states` parameter. It is a separate
     interface rather than a method on `PaypalPaymentQueryInterface`, which shipped in 1.7 — adding to that
     one would break every shop implementing it instead of decorating it. `PaypalPaymentQuery` implements
     both, and the container aliases both to it. The settleable states deliberately cover `cancelled` and
     `failed` as well, so a late webhook can find its payment and log the mismatch rather than throw.
   - `Sylius\PayPalPlugin\Model\PayPalCapture` is new: a read-only view of the capture buried in
     `purchase_units[0].payments.captures[0]`, built with `PayPalCapture::fromPayPalOrder()`, which answers
     `null` for an order that has no capture yet. It owns the conversion of PayPal's decimal string into
     Sylius minor units and carries the `STATUS_*` constants the settlement processor used to declare.
   - `PaymentSettlementProcessorInterface` still completes a payment whose capture does not match the
     amount or currency it expected. The money is real, and refusing to settle would leave a paid order
     unpaid — the worse of the two errors. The mismatch is no longer only a log line, though: the capture's
     `captured_amount` and `captured_currency_code` now land in the payment details, so it can be seen in
     the admin panel and reconciled instead of being looked for in logs.
   - `PaypalPaymentQuery` narrowed the return type of its finders to `PaymentInterface`. None of them ever
     answered `null` — they throw `PaymentNotFoundException` — so reading code can stop null-checking today.
     `PaypalPaymentQueryInterface`, which shipped in 1.7, still declares `?PaymentInterface` on its three
     methods: narrowing an interface forces every implementation to narrow with it, and a minor cannot ask
     that. **Those three will be narrowed in 3.0.** If you implement that interface rather than decorating
     it, you have until then to drop the `?`.
   - `GenericApi::get()` checks the status code. A PSR-18 client does not throw on 4xx or 5xx, so PayPal's
     error document used to be decoded and handed back as ordinary data, leaving callers to guess from its
     shape whether the call had failed. Anything but `200` now throws `PayPalPluginException`, which the
     webhook dispatcher treats as transient. `PayPalRefundDataProvider` therefore answers
     `PayPalWrongDataException` for a response it genuinely cannot read, and `WebhookIdProvider` reports a
     failed lookup instead of an empty one.
   - `Sylius\PayPalPlugin\Exception\InvalidPayerDataException` is new and replaces four assertions in
     `PayPalPaymentSourceProvider`. An order paid with a redirect method needs a billing address, a country
     code PayPal accepts, a payer name and an email; missing any of them used to raise Webmozart's
     `InvalidArgumentException`, with no type of its own to catch. It is declared on
     `PayPalPaymentSourceProviderInterface::provide()` alongside `UnsupportedPayPalPaymentSourceException`.
   - `PayPalFundingSourcesConfigurationProviderInterface` gained `isTrustlyEnabled(ChannelInterface)`.
     Implement it if you implement that interface from scratch rather than decorating the shipped provider.
   - `PayPalClient` no longer fails when no channel is in context. The `PayPal-Partner-Attribution-Id`
     header is omitted and a warning logged instead of an exception being thrown, which is what lets the
     webhook handler and the CLI sweeper reach PayPal at all.
   - `PayPalExtension` gained two trailing optional constructor arguments, `LocaleContextInterface` and
     `LocaleProcessorInterface`. `sylius_paypal_web_sdk_instance_config()` uses them to resolve the shop's
     current locale for Pay Later messaging when the caller does not pass one explicitly. Not passing them
     is deprecated and keeps today's behavior (no locale resolved automatically).

1. #### Venmo is available on all four wallet-button placements.

   A `<venmo-button>` on the product page, cart page, checkout/select-payment wallet button, and
   `/pay-with-paypal/{orderToken}/{paymentId}`, next to the existing PayPal tile. One new, **opt-in** admin
   toggle on the PayPal payment method's gateway config controls it:

   - `venmo_enabled` — defaults to `false`, including for existing payment methods.

   This is the opposite default from `pay_later_enabled` and `messaging_enabled`. Venmo is newer and less
   exercised in production than Pay Later, and it is US-only, so turning it on is a deliberate merchant
   decision rather than something every shop should suddenly start offering.

   It is not a new Stimulus controller — Venmo reuses the same `paypal-web-sdk` and
   `paypal-payment-wallet-button` controllers the PayPal and Pay Later buttons already use, so there is
   nothing new to register in `controllers.json`.

   **Orders created for Venmo now actually carry `payment_source.venmo`.** Every entry point that starts a
   PayPal attempt names the funding source it used — `startAttempt(paymentSource)` on the payment page, and
   a `paymentSource` query parameter on the product/cart/checkout-select create-order requests — and
   `CreatePayPalOrderFromCartAction`/`CreatePayPalOrderFromPaymentPageAction` now read and record it before
   capturing, the same way `CreatePayPalOrderAction` already did. Before this, every Venmo payment silently
   created its order as `payment_source.paypal`, because `PayPalPaymentSourceProvider` had no `venmo` case
   and three of the four placements never read a payment source from the request at all.
