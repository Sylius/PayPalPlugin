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
   now also stores the region on the order's addresses — previously it was dropped.

   If your shop overrode `pay_from_cart_page.html.twig` or `pay_from_product_page.html.twig`, drop the
   `updateOrderUrl` and `availableCountries` values from the `stimulus_controller()` call; they are no longer
   read. Leaving them in place is harmless.

1. #### Orders addressed in the PayPal wallet now name their payment source.

   Cart and product placements send `payment_source.paypal.experience_context` instead of the deprecated
   `application_context`, because PayPal reads the shipping callback configuration only from there. The two
   are mutually exclusive — sending `shipping_preference` or `user_action` in both makes PayPal reject the
   order — so every other flow, including the checkout payment page and the legacy card page, keeps using
   `application_context` untouched.

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

   Each is superseded by a v6 Web SDK equivalent and exists only to support the legacy, full-page
   `pay_with_paypal.html.twig` checkout (itself planned for a v6 `card-fields` + 3D Secure rework in a future
   release). They keep working unchanged in 2.1 and only emit a deprecation notice.

   | Deprecated route | Replacement |
   |---|---|
   | `sylius_paypal_shop_pay_with_paypal_form` | `PayPalButtonsController::renderPaymentPageButtonsAction` (the v6 payment-page placement) |
   | `sylius_paypal_shop_create_paypal_order` | `sylius_paypal_shop_create_paypal_order_from_cart` / `..._from_payment_page` |
   | `sylius_paypal_shop_complete_paypal_order` | `sylius_paypal_shop_process_paypal_order` / `..._complete_paypal_order_from_payment_page` |
   | `sylius_paypal_shop_cancel_checkout_payment` | `sylius_paypal_shop_cancel_payment` / `..._cancel_order` |
   | `sylius_paypal_shop_cancel_last_payment` | none — no longer needed once the legacy page is removed |
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

   The `orderID` returned by the deprecated `sylius_paypal_shop_create_paypal_order` route is unchanged and not
   part of this: that route serves the legacy `pay_with_paypal.html.twig` page, which still reads it.

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
   +        private ?PayPalShippingAddressFactoryInterface $shippingAddressFactory = null,
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
   +    <argument type="service" id="sylius_paypal.factory.paypal_shipping_address" />
    </service>
   ```

   The first three throw a `\RuntimeException` when they are actually needed — generating a return URL,
   completing the order, or detaching a mismatched payment. The last two degrade instead: the action behaves
   as it did in 2.0, which means the shipping method the buyer chose in the wallet is not applied and the
   region is not stored. The first of those two fails the amount check and sends the buyer back to the
   checkout instead of the thank-you page.

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
