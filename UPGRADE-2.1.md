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

1. #### The shipping-address callback keeps working, under its v6 name.

   v5's single `onShippingChange` handler is split in v6 into `onShippingAddressChange` and
   `onShippingOptionsChange`, passed to the payment session instead of to `paypal.Buttons()`. The placements
   register `onShippingAddressChange`, which still posts to `sylius_paypal_shop_update_paypal_order` — that
   route is **not** deprecated and remains the mechanism that keeps the PayPal order total in line with the
   address the buyer picks inside the wallet.

   Only the cart and product ("shortcut") placements register it, because only they reach PayPal without a
   shipping address. If your shop overrode either of those two templates, the override must pass the
   controller's `updateOrderUrl` and `availableCountries` values, or the buyer's address change will no longer
   be priced - PayPal then captures a total that no longer matches the Sylius order, and the payment is
   rejected on return.

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

1. #### The create/capture-order JSON contract is now consistent across the three v6 placements.

   The same value used to be spelled three different ways, and `orderID` meant two different things depending
   on the endpoint — the PayPal order id from the cart placement, the *Sylius* order id from
   `ProcessPayPalOrderAction`. The v6 placements now use one spelling throughout:

   | Endpoint | New keys | Old key, still sent |
   |---|---|---|
   | `CreatePayPalOrderFromCartAction` | `id`, `orderId`, `status` | `orderID` (PayPal order id) |
   | `CreatePayPalOrderFromPaymentPageAction` | `id`, `orderId`, `status` | `order_id` (PayPal order id) |
   | `ProcessPayPalOrderAction` | `syliusOrderId`, `orderId`, `status` | `orderID` (**Sylius** order id) |
   | `CompletePayPalOrderFromPaymentPageAction` | `orderId`, `status` added next to `return_url` | — nothing renamed |

   **This is not a break in 2.1.** Every old key is still sent alongside its replacement, with the same value
   and the same meaning it had in 2.0, so JavaScript reading the old names keeps working. The old keys are
   deprecated and **will be removed in 3.0** — move your code to the new names before then.

   The `orderID` returned by the deprecated `sylius_paypal_shop_create_paypal_order` route is unchanged and not
   part of this: that route serves the legacy `pay_with_paypal.html.twig` page, which still reads it.

1. #### The BN code (`PayPal-Partner-Attribution-Id`) is read through one place, `PayPalConfigurationProviderInterface::getPartnerAttributionId()`, everywhere it previously wasn't
   (`PayWithPayPalFormAction`, the API-Platform payment configuration, `PayPalClient`). The stored value itself
   is unchanged in this release.

1. #### The following constructor signatures have gained new optional (nullable) arguments, following this
   package's existing deprecation pattern — not passing them is deprecated and will be required in 3.0:

   `Sylius\PayPalPlugin\Controller\PayWithPayPalFormAction`: `?PayPalConfigurationProviderInterface $payPalConfigurationProvider = null`

   `Sylius\PayPalPlugin\ApiPlatform\PayPalPayment`: `?PayPalConfigurationProviderInterface $payPalConfigurationProvider = null`

   `Sylius\PayPalPlugin\Controller\PayPalButtonsController`: `?PayPalWebSdkConfigurationProviderInterface $webSdkConfigurationProvider = null`
   — unlike the other two this one has no usable fallback: the v6 placements cannot be rendered without it, so
   a controller constructed without it throws a `\RuntimeException` when a placement is rendered. If you
   instantiate or decorate this class yourself, pass `sylius_paypal.provider.paypal_web_sdk_configuration`.

## Still open for a future 2.1.x / 2.2

- Migrating `pay_with_paypal.html.twig` from Hosted Fields to the v6 `card-fields` component, with 3D Secure
  handling. PayPal's SDD does not currently document a plain one-time card payment + 3DS flow (only a
  save-card/vault variant), so this needs a confirmed API shape before it can start.
- Confirming the correct final BN code value (currently unchanged pending an open question to PayPal).
