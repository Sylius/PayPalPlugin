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

   A new shared template, `templates/_paypal_web_sdk.html.twig`, is included by all three placements and is not
   meant to be included or overridden on its own.

1. #### The shipping-address callback keeps working, under its v6 name.

   v5's single `onShippingChange` handler is split in v6 into `onShippingAddressChange` and
   `onShippingOptionsChange`, passed to the payment session instead of to `paypal.Buttons()`. The placements
   register `onShippingAddressChange`, which still posts to `sylius_paypal_shop_update_paypal_order` — that
   route is **not** deprecated and remains the mechanism that keeps the PayPal order total in line with the
   address the buyer picks inside the wallet.

   If your shop overrode any of the three placement templates, the override must pass the controller's
   `updateOrderUrl` and `availableCountries` values, or the buyer's address change will no longer be priced -
   PayPal then captures a total that no longer matches the Sylius order, and the payment is rejected on return.

1. #### Per-channel toggles for Pay Later, Venmo and messaging.

   Three new gateway-config fields — `paylater_enabled`, `venmo_enabled`, `messaging_enabled` — default to
   `true` for both new and existing (pre-2.1) payment methods. Eligibility (from PayPal's own API) remains the
   primary gate for all three; these are merchant opt-outs, editable from the payment method's admin form.

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

   `Sylius\PayPalPlugin\Api\UpdateOrderAddressApi`, used only by the legacy Payum-redirect completion flow
   (`Sylius\PayPalPlugin\Payum\Action\CompleteOrderAction`), is deprecated for the same reason and will be
   removed alongside it in 3.0 — it is not deleted in 2.1.

1. #### The create/capture-order JSON contract is now consistent across the three v6 placements.

   All three "create order" endpoints (`CreatePayPalOrderFromCartAction`, `CreatePayPalOrderFromPaymentPageAction`)
   now return `{"id": <sylius order id>, "orderId": <paypal order id>, "status": <payment state>}` — previously
   the cart placement returned `orderID` and the payment-page placement returned `order_id`. All "capture"
   endpoints (`ProcessPayPalOrderAction`, `CompletePayPalOrderFromPaymentPageAction`) now include `orderId` and
   `status` in their response. `ProcessPayPalOrderAction`'s response key that carries the *Sylius* order id
   (previously also confusingly named `orderID`) is now `syliusOrderId`.

   If you have JavaScript overriding or extending the three button templates and reading these response keys
   directly, update it to the new names.

1. #### The BN code (`PayPal-Partner-Attribution-Id`) is read through one place, `PayPalConfigurationProviderInterface::getPartnerAttributionId()`, everywhere it previously wasn't
   (`PayWithPayPalFormAction`, the API-Platform payment configuration, `PayPalClient`). The stored value itself
   is unchanged in this release.

1. #### The following constructor signatures have gained new optional (nullable) arguments, following this
   package's existing deprecation pattern — not passing them is deprecated and will be required in 3.0:

   `Sylius\PayPalPlugin\Controller\PayWithPayPalFormAction`: `?PayPalConfigurationProviderInterface $payPalConfigurationProvider = null`

   `Sylius\PayPalPlugin\ApiPlatform\PayPalPayment`: `?PayPalConfigurationProviderInterface $payPalConfigurationProvider = null`

## Still open for a future 2.1.x / 2.2

- Moving the button JavaScript out of Twig `<script>` blocks into Stimulus controllers.
- Migrating `pay_with_paypal.html.twig` from Hosted Fields to the v6 `card-fields` component, with 3D Secure
  handling. PayPal's SDD does not currently document a plain one-time card payment + 3DS flow (only a
  save-card/vault variant), so this needs a confirmed API shape before it can start.
- Confirming the correct final BN code value (currently unchanged pending an open question to PayPal).
