# UPGRADE FROM `2.1` TO `2.2`

## Required actions

1. **Add the plugin's front-end package to your asset build.**

   Every PayPal placement now renders Stimulus controllers from this package's npm package (`assets/shop`)
   instead of inline scripts. Until they are part of your build, the placements render nothing and report no
   error.

   Add the package to your app's `package.json`:

   ```json
   "dependencies": {
       "@sylius/paypal-plugin": "file:vendor/sylius/paypal-plugin/assets/shop"
   }
   ```

   Register all of its controllers in the `controllers.json` your shop build passes to
   `Encore.enableStimulusBridge()` (typically `assets/shop/controllers.json`):

   ```json
   "@sylius/paypal-plugin": {
       "paypal-web-sdk": { "enabled": true, "fetch": "lazy" },
       "paypal-payment-wallet-button": { "enabled": true, "fetch": "lazy" },
       "paypal-payment-card-fields": { "enabled": true, "fetch": "lazy" },
       "paypal-message": { "enabled": true, "fetch": "lazy" },
       "paypal-payment-google-pay": { "enabled": true, "fetch": "lazy" },
       "paypal-payment-apple-pay": { "enabled": true, "fetch": "lazy" },
       "paypal-payment-redirect-button": { "enabled": true, "fetch": "lazy" }
   }
   ```

   Then run `yarn install && yarn build`. To verify, load a product page and check that the browser fetches
   the controller chunk and the PayPal button loses its `hidden` attribute.

1. **Enable the `JavaScript SDK v6` feature on the PayPal REST app behind your client id.**

   Do it in the PayPal Developer Dashboard, on the sandbox and the live app separately, and on the app that
   hand-pasted sandbox credentials come from. Without it the server-side calls keep working, but in the browser
   `findEligibleMethods()` answers `403 NOT_AUTHORIZED` / `PERMISSION_DENIED`, the SDK throws `SdkInitError`, and
   every placement gives up with only a `console.error`:

   - the product and cart buttons stay `hidden`;
   - the checkout payment step renders neither the wallet button nor the card fields;
   - Pay Later messaging stays hidden.

   To verify, load a product page and check that the button loses its `hidden` attribute and that the console
   carries no `403`.

1. **Run the migrations.** Package tracking adds the `sylius_paypal_plugin_shipment_tracking` table:

   ```bash
   bin/console doctrine:migrations:migrate
   ```

1. **Re-register the webhook event types.**

   Set `sylius_paypal.webhook_base_url` to the shop's public base URL first. From the CLI there is no request
   to build an absolute URL from, so the router falls back to `http://localhost/…`. Then run:

   ```bash
   bin/console sylius-paypal:register-webhook-event-types
   ```

   A webhook registered before 2.2 is subscribed to `PAYMENT.CAPTURE.REFUNDED` only, and re-enabling the
   payment method does not update it. The command `PATCH`es the existing webhook, which keeps its id, or creates
   one for a shop that has none. Without it, a Trustly payment settles only through the return page and
   `sylius-paypal:complete-payments`.

1. **Make the shipping callback route reachable.**

   The new `sylius_paypal_order_shipping_callback` route (`POST /paypal/order-shipping-callback`) lives in
   `@SyliusPayPalPlugin/config/routes/callback.yaml`, outside the shop's `/{_locale}` prefix. A shop importing
   `@SyliusPayPalPlugin/config/routes.yaml` gets it automatically. A shop importing the files under
   `config/routes/` one by one has to import `callback.yaml` itself, without a prefix.

   PayPal calls the route from its own servers, and the plugin declares it on an order only when the generated
   URL is `https`. Check `router.request_context.host` and `router.request_context.scheme` if the URL comes out
   wrong. Without it the wallet offers no shipping options.

1. **Update your Content-Security-Policy, if your shop sends one.**

   - The Web SDK loads from `sylius_paypal.web_url`: `https://www.paypal.com` in production and
     `https://www.sandbox.paypal.com` in sandbox mode. The v5 SDK always loaded from `https://www.paypal.com`.
   - Google Pay, when enabled: allow `pay.google.com` in `script-src`, and
     `pay.google.com google.com account.google.com www.google.com` in `connect-src`. A nonce-based CSP also needs
     the nonce on the `pay.js` tag and on `PaymentsClient`, which the plugin does not pass; override the tile
     template and the controller.
   - Apple Pay, when enabled: allow `applepay.cdn-apple.com` in `script-src`.

1. **Review your template and hookable overrides.**

   The templates and hookables listed in [Templates and Twig hooks](#templates-and-twig-hooks) were rewritten
   for Web SDK v6. An override of one of them renders v5 markup with no SDK behind it, or is no longer rendered
   at all.

1. **New Composer requirements:** `ext-openssl` and `psr/cache`.

## Behaviour changes

1. **Express checkout completes the purchase in the PayPal wallet.** The cart and product page buttons used to
   land the buyer on the checkout summary to press "Place order" a second time. `ProcessPayPalOrderAction`
   (`sylius_paypal_shop_process_paypal_order`) now captures the payment and completes the order in the same
   request, and sends the buyer to the thank-you page.

   - When the order total dropped below the amount approved in the wallet, the PayPal order is updated to the
     new total and the order is completed. If PayPal refuses the update, it is treated as a mismatch.
   - When the total grew, the payment is detached, the order is reprocessed and the buyer is returned to the
     checkout summary to retry.
   - The buyer's phone number from PayPal's `payer` payload is written onto the order's addresses and onto a
     newly created customer.

1. **The wallet offers the shop's real shipping methods.** The cart and product placements used to write a
   placeholder address (`Temp`/`Temp`/`Temp`) onto the order and send PayPal one default method. PayPal now
   calls `sylius_paypal_order_shipping_callback`, which answers with every method eligible for the chosen
   address, priced by the same Sylius services as the regular checkout, or with a `422` naming the reason. The
   callback writes nothing to the order. Each option is labelled with the shipping method's name in the order's
   locale, since a callback from PayPal's servers carries no shop locale.

1. **Addresses keep the region, and addresses entered in the Sylius checkout are kept.**

   - Both payloads that carry a shipping address to PayPal, the purchase unit and the pre-capture address patch
     in `UpdateOrderAddressApi`, send the region as `admin_area_1`. That is the province code without the country
     prefix (`US-TX` → `TX`), or the province name when there is no code.
   - PayPal's `admin_area_1` is resolved back to a province by code, first as `"{country_code}-{admin_area_1}"`
     and then bare. A region that does not resolve is stored as the province name.
   - An order that reaches the wallet with a shipping address is sent as `SET_PROVIDED_ADDRESS` and keeps its
     addresses untouched. Addresses are built from PayPal's echo only for an order that carried none.

1. **Card payments are refused when 3D Secure does not authorise them.**

   - Card orders send `payment_source.card.attributes.verification.method` as `SCA_WHEN_REQUIRED`, so 3D Secure
     runs wherever regulation or the card network requires it. **Shops in SCA regions will see real
     challenges where they saw none before.**
   - Before capturing, `sylius_paypal_shop_complete_paypal_order` fetches the order and applies PayPal's
     decision table over `enrollment_status`, `authentication_status` and `liability_shift`.
   - A refused authentication cancels the payment (the order stays payable), flashes
     `sylius_paypal.three_d_secure_retry` or `sylius_paypal.three_d_secure_declined`, and returns a `return_url`
     to the payment page, or to the order when the buyer cannot retry.
   - An order with no `authentication_result` is captured as before.

1. **Pay Later is on by default.** The Pay Later button and the `<paypal-message>` financing message render on
   the product, cart and checkout pages whenever PayPal reports the buyer eligible. Their toggles,
   `pay_later_enabled` and `messaging_enabled`, default to `true`, including for existing payment methods. All
   other new methods are opt-in (see [Payment methods](#payment-methods)).

1. **A capture that does not complete cancels the payment.** `sylius_paypal_shop_complete_paypal_order`
   (`CompletePayPalOrderAction`) cancels the payment when the capture does not complete it, for instance a
   capture PayPal reports as `PENDING`. It flashes `sylius_paypal.something_went_wrong` and returns a
   `return_url` to the payment page. In 2.1 it returned the thank-you page and left the payment `processing` for
   `sylius-paypal:complete-payments`. A capture PayPal completes later for such a payment is kept as
   `paypal_late_capture` (see [Payment details](#models-payment-details-and-the-paypal-payload)).

1. **Starting a new attempt ends the previous one.**

   - `sylius_paypal_shop_create_paypal_order` and `sylius_paypal_shop_create_paypal_order_from_payment_page`
     cancel a PayPal payment left in `processing` before creating a new PayPal order. The order gets a fresh
     payment with the same method. Another gateway's processing payment is untouched.
   - `sylius_paypal_shop_payment_error` cancels the payment of the PayPal order the wallet window failed on and
     reprocesses the order. A payment that cannot take the `cancel` transition is left alone.
   - After an amount mismatch, `sylius_paypal_shop_complete_paypal_order_from_payment_page` cancels the payment,
     reprocesses the order and flushes, so the order keeps a payment to pay with. The cancelled payment stays
     attached to the order, so the checkout summary lists both. The plugin marks the cancelled one through the
     `paypal_cancelled_state_label` hookable, and the buyer gets the new `error` flash
     `sylius_paypal.order_total_changed`.

1. **A payment waiting at the buyer's bank cannot be cancelled by accident.** While a redirect payment is
   `processing` and carries a `payer_action_url`, `PaymentStateManager::cancel()` does nothing. As a result:

   - `POST /create-pay-pal-order/{token}` answers `409`;
   - `/pay-with-paypal/{orderToken}/{paymentId}` redirects to the order page;
   - the thank-you page hides "Change payment method" and the different-amount notice;
   - the order page shows a notice that the transfer is on its way, with the link back to the bank, instead of
     the payment method form.

   `sylius_paypal_shop_cancel_payment` and `sylius_paypal_shop_cancel_last_payment` stay unguarded, as the
   buyer's way out.

1. **Redirect payments settle asynchronously.** PayPal captures a Trustly order itself on approval
   (`processing_instruction: ORDER_COMPLETE_ON_PAYMENT_APPROVAL`). From that moment the PayPal order reports
   `COMPLETED` while the capture stays `PENDING` for up to seven days. The Sylius payment stays `processing`
   and the order `awaiting_payment` until the capture completes. **Do not ship on a `processing` payment.**

1. **`sylius-paypal:complete-payments` settles on the capture status, not the order status.** It delegates to
   `PaymentSettlementProcessorInterface`, which reads the capture. Wallet and card payments settle as before;
   an order reporting `COMPLETED` with no capture recorded is now left alone. A capture whose amount or currency
   differs from the payment still completes it, and records `captured_amount` and `captured_currency_code` in
   the payment details.

1. **The webhook answers `503` when it could not finish an event**, so PayPal delivers it again for about three
   days. It used to answer `204` whatever happened.

   - A failure the plugin recognises as permanent, a refund document with no link back to the capture, is still
     answered `204`.
   - An event for an order the shop does not have answers `204` instead of `404`.
   - Once `PAYMENT.CAPTURE.COMPLETED` is subscribed, every card and wallet payment delivers one too. The handler
     finds those payments already completed and does nothing.

1. **The plugin caches in its own pool.** The shipping callback certificates and the webhook id refresh cooldown
   moved from `cache.app` to `sylius_paypal.cache`, a private pool parented to `cache.app` and tagged
   `cache.pool`. It inherits your `framework.cache.app` adapter. Existing entries are not migrated, so it costs
   one extra certificate download and one cooldown reset. `bin/console cache:pool:clear sylius_paypal.cache`
   clears both. To put it on another backend:

   ```yaml
   services:
       sylius_paypal.cache:
           parent: cache.adapter.redis
           tags: ['cache.pool']
   ```

1. **The payment page tells the payer that PayPal processes their data**, as SDD §4.1.4 requires. The sentence
   and the link to PayPal's privacy notice are in English only; `fr` and `nl` fall back to it. If your own
   privacy notice carries PayPal's prescribed paragraph instead, disable the hookable:

   ```yaml
   sylius_twig_hooks:
       hooks:
           'sylius_paypal.shop.pay_with_paypal.content':
               privacy_notice:
                   enabled: false
   ```

1. **Amounts PayPal is told about are formatted for the currency.** `Sylius\PayPalPlugin\AmountUtils` formats
   HUF, JPY and TWD without decimals (`toPayPalValue(int $amount, string $currencyCode)`) and reads amounts back
   (`toMinorUnits(string $value)`). In those currencies a Sylius amount that is not a round hundred is rounded
   on the way out. Only `FindEligibleMethodsApi` and `PayPalCapture` use it so far.

1. **A gateway config missing its client id or secret fails early.** `CacheAuthorizeClientApi` and
   `SellerWebhookRegistrar` throw an `InvalidArgumentException` naming the missing key, instead of sending PayPal
   an empty string.

1. **`PayPalClient` works without a channel in context.** It omits the `PayPal-Partner-Attribution-Id` header
   and logs a warning instead of throwing, which lets the webhook handler and the CLI reach PayPal.

1. **The PayPal approval window and the buttons use the current shop locale**, resolved by
   `CurrentPayPalLocaleProviderInterface`, instead of the cart's locale. A locale PayPal does not support, such
   as `es_MX`, falls back to PayPal's default language.

1. **The thank-you page's "Change payment method" button** shows only while the order's payment state is
   `awaiting_payment`. It used to show whenever the last payment was not completed.

## New features and configuration

### Payment methods

Each method is controlled per payment method by a toggle in its gateway config. These keys are read through
`PayPalGatewayConfig` and `PayPalFundingSourcesConfigurationProviderInterface`.

| Key | Default | Where |
|---|---|---|
| `pay_later_enabled` | `true` | Pay Later button on the product, cart, checkout and payment pages |
| `messaging_enabled` | `true` | `<paypal-message>` on the product, cart and checkout pages |
| `venmo_enabled` | `false` | `<venmo-button>` on the product, cart, checkout and payment pages |
| `google_pay_enabled` | `false` | payment page |
| `apple_pay_enabled` | `false` | payment page |
| `trustly_enabled` | `false` | payment page |
| `card_three_d_secure_always` | `false` | sends `SCA_ALWAYS` instead of `SCA_WHEN_REQUIRED` for card payments |

The defaults apply to existing payment methods too, whose stored config does not carry these keys yet.

Prerequisites the plugin cannot handle for you:

- **Google Pay**: the Google Pay capability on the PayPal account, and the CSP entries above. The button is drawn
  by Google's SDK (`https://pay.google.com/gp/p/js/pay.js`).
- **Apple Pay**:
  - the Apple Pay capability on the PayPal account;
  - every domain and subdomain showing the button registered in PayPal's Apple Pay settings;
  - PayPal's domain association file served at `/.well-known/apple-developer-merchantid-domain-association`
    over HTTPS, as `application/octet-stream`, without a redirect;
  - the channel's shop billing country filled in, because Apple needs the merchant's `countryCode`. A channel
    without it renders no tile and logs why to the browser console;
  - an Apple Developer Program membership, only for sandbox testing.

  The button is Apple's `<apple-pay-button>` from `https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js`.
  Outside Safari it hands the payment off to an iPhone running iOS 18 or later through a QR code.
- **Trustly**: the `TRUSTLY` capability on the PayPal account
  (`https://www.paypal.com/bizsignup/add-product?product=trustly&capabilities=TRUSTLY&country.x=<cc>`; sandbox
  uses `/bizsignup/entry`). It is available in Austria, Germany, Denmark, Estonia, Spain, Finland, Great
  Britain, Lithuania, Latvia, the Netherlands, Norway and Sweden, in `EUR`, `DKK`, `SEK`, `GBP` or `NOK`. There
  is no vaulting, no chargebacks and no shipping callback; refunds work for up to 365 days. Trustly does not
  exist in Web SDK v6, so the tile redirects to PayPal server-side and works even without the v6 app feature.

### Package tracking

When a shipment of an order paid with PayPal is shipped with a carrier, the plugin sends the parcel to PayPal's
Add Tracking API (`POST /v2/checkout/orders/{id}/track`). The items are matched to the order's items by `sku`
(`ProductVariant::getCode()`). The feature lives under `Sylius\PayPalPlugin\PackageTracking\` and stores its
state in the plugin-owned `sylius_paypal_plugin_shipment_tracking` table; the core `Shipment` entity is
unchanged.

1. **Admin.** For orders paid with PayPal, the ship form on the order page and in the shipment list gains a
   carrier selector:

   - `OTHER` reveals a required free-text carrier name;
   - a selected carrier requires a tracking number of at most 64 characters;
   - without a carrier, nothing is sent to PayPal unless the shipment already has a tracking record with a
     carrier;
   - each shipment on the order page shows its sync state: pending, synced or failed;
   - a shipment shipped in the meantime, for example in another tab, is not shipped again.

   Orders paid with any other method keep the stock Sylius form.

1. **Carriers.** `sylius_paypal.tracking.carriers` defaults to a curated subset of
   [PayPal's carrier codes](https://developer.paypal.com/docs/tracking/reference/carriers/). Listing your own
   codes replaces that default. `OTHER` is always appended. Labels come from `sylius_paypal.carrier.<CODE>`
   translation keys and fall back to the raw code.

   ```yaml
   sylius_paypal:
       tracking:
           carriers:
               - INPOST_PACZKOMATY
               - DPD_POLAND
               - POCZTA_POLSKA
   ```

1. **Failures never block shipping.** `ShipmentTrackingDispatcher` logs anything thrown to the `paypal` channel.

   - A failure PayPal will never accept, such as an ineligible order status, a PayPal order without items, a
     missing tracking number or carrier, or an unresolvable capture id, is recorded as `failed` and not
     retried.
   - Anything else is recorded and rethrown, so Messenger's retry strategy applies on an async transport.
   - A shipment not yet `shipped` raises `ShipmentTrackingNotReadyException`, so an async worker retries once
     the ship transaction has committed.

   Retry pending and failed records by hand with
   `bin/console sylius-paypal:send-shipment-tracking [--batch-size=100]`.

1. **Async.** The call is the `Sylius\PayPalPlugin\PackageTracking\Message\SendShipmentTracking` message on
   `sylius_paypal.package_tracking_bus`. It is handled synchronously, inside the ship transaction, unless you
   route it:

   ```yaml
   framework:
       messenger:
           routing:
               'Sylius\PayPalPlugin\PackageTracking\Message\SendShipmentTracking': async
   ```

1. **Admin API.** `PATCH /api/v2/admin/shipments/{id}/ship` takes an optional `carrier` and `carrierNameOther`
   next to `trackingCode`:

   ```json
   {"trackingCode": "1Z999AA10123456784", "carrier": "UPS"}
   ```

   For an order paid with PayPal the carrier rules above apply. A violation answers `422` and the shipment
   stays `ready`. See [Routes and HTTP responses](#routes-and-http-responses) for the fields the operation
   rejects.

### Other configuration

1. `sylius_paypal.test_buyer_country` (env `SYLIUS_PAYPAL_TEST_BUYER_COUNTRY`, default `null`) sends
   `testBuyerCountry` to the Web SDK, in sandbox mode only.
1. The `sylius_paypal.repository.query.pay_pal_payment.settleable_states` parameter lists the payment states a
   webhook may settle. It includes `cancelled` and `failed`, so a late capture finds its payment.

## Changes affecting customizations

### Templates and Twig hooks

1. **Rewritten for Web SDK v6.** An override of any of these renders v5 markup with no SDK behind it, with no
   error. Diff it against the new template or drop it.

   - `@SyliusPayPalPlugin/pay_from_cart_page.html.twig`, `pay_from_product_page.html.twig` and
     `pay_from_payment_page.html.twig`:
     - they render `data-controller="sylius--paypal-plugin--paypal-web-sdk"` and PayPal's web components;
     - `#paypal-button-container` is gone;
     - the cart and product templates no longer receive `completeUrl`, and follow the `return_url` the process
       endpoint returns;
     - drop `updateOrderUrl` and `availableCountries` from their `stimulus_controller()` call, which no longer
       reads them.
   - `@SyliusPayPalPlugin/pay_with_paypal.html.twig` now extends `@SyliusShop/checkout/common/layout.html.twig`
     and receives its variables from `PayPalPaymentPageContextProviderInterface`. `billing_address` is now
     `billingAddress`, and `available_countries`, `client_id`, `client_token`, `merchant_id`, `order_token` and
     `partner_attribution_id` are gone.

1. **New hooks on the payment page.** `sylius_paypal.shop.pay_with_paypal.content` carries `flashes` (200),
   `methods` (100) and `privacy_notice` (0). Each payment method is a hookable on
   `sylius_paypal.shop.pay_with_paypal.content.methods`:

   | Hookable | Priority |
   |---|---|
   | `paypal` | 600 |
   | `paypal_messaging` | 500 |
   | `venmo` | 400 |
   | `google_pay` | 300 |
   | `apple_pay` | 200 |
   | `redirect_methods` | 100 |
   | `card` | 0 |

   A tile of your own belongs on the inner hook; anything else on the outer one.

1. **A custom `<paypal-message>` placement** has to pass `amount` as a string with at most two decimals (e.g.
   `"29.41"`), not a number, and has to create the SDK instance with `paypal-messages` in its `components`
   before awaiting `customElements.whenDefined('paypal-message')`. The element is defined only by that call.
   The shipped one is the `sylius--paypal-plugin--paypal-message` controller.

1. **New hookables elsewhere:**

   - `paypal_messaging` and `paypal_venmo` on the product, cart and checkout placements. Venmo renders
     `<venmo-button data-sylius-paypal-venmo-button>`, which the placement's `paypal-web-sdk` controller finds
     through its `venmoButtonSelector` value; an override has to keep the attribute and the hook container.
   - `paypal_cancelled_state_label` in `sylius_shop.checkout.complete.content.form.summary.statuses.payments.list`.
   - `paypal_awaiting_payer_action` in the shared order summary, and the
     `sylius_shop.order.show.content.paypal_awaiting_payer_action` hook.
   - `paypal_late_capture` and `refund_paypal_late_capture` on the admin order's payments.
   - `paypal_tracking_state` in `sylius_admin.order.show.content.sections.shipments.item.general`.
   - The admin ship form's fields: `tracking`, `carrier`, `carrier_name_other` and `submit`, on
     `sylius_paypal.admin.shipment.ship_form` (order page) and `sylius_paypal.admin.shipment.index.ship_form`
     (shipment list).
   - `flashes` (priority `50`) in `sylius_shop.checkout.select_payment.content`. Disable it if your shop already
     renders flashes on that step.

1. **Core hookables and templates the plugin replaces.** A shop override of the core template behind these is
   no longer rendered:

   - `sylius_admin.order.show.content.sections.shipments` → `items`;
   - the `template` prop of `sylius_admin.order.show.content.sections.shipments.item.actions` → `ship`, now
     `@SyliusPayPalPlugin/admin/shipment/component/ship_gate.html.twig`;
   - the `ship_with_tracking_code` grid action template (`sylius_grid.templates.action`);
   - `sylius_shop.order.show.content` → `form`, now `@SyliusPayPalPlugin/shop/order/show/content/form.html.twig`.

1. **The admin ship form for PayPal orders.**

   - It is the `sylius_paypal_admin:shipment:ship_form` live component. Its `ship` action validates the form and
     applies the transition the way Sylius' ship route does, including the
     `sylius.shipment.pre_ship`/`post_ship` events.
   - The carrier fields are an unmapped `paypal_tracking` sub-form (`ShipmentTrackingType`, backed by
     `ShipmentTrackingData`), reached as `form.paypal_tracking.carrier` and
     `form.paypal_tracking.carrier_name_other`. They are validated by the `ShipmentTrackingCarrier` constraint
     in the `sylius` group.
   - An override of `@SyliusPayPalPlugin/admin/shipment/component/ship.html.twig` or `ship_from_index.html.twig`
     has to keep submitting through the component (`data-action="live#action:prevent"`,
     `data-live-action-param="ship"`). Posting to Sylius' ship routes ships without saving the carrier.
   - The form renders with `render_rest: false`, so fields a shop added to `ShipmentShipType` are not rendered for
     PayPal orders.

1. **Priority change:** `paypal_checkout` on `sylius_shop.product.show.content.info.summary` moved from `50` to
   `75`.

1. **New Twig functions:**

   - `sylius_paypal_is_messaging_enabled()`;
   - `sylius_paypal_web_sdk_script_url()`;
   - `sylius_paypal_web_sdk_instance_config()`;
   - `sylius_paypal_is_awaiting_payer_action(payment)`;
   - `sylius_paypal_is_refunded_to_paypal_wallet()`;
   - `sylius_paypal_shipment_is_paid_with_paypal()`;
   - `sylius_paypal_shipment_tracking()`.

### Routes and HTTP responses

1. **New routes:**

   | Route | Path |
   |---|---|
   | `sylius_paypal_order_shipping_callback` | `POST /paypal/order-shipping-callback` (outside `/{_locale}`), `Controller\ShippingCallbackAction` |
   | `sylius_paypal_shop_redirect_return` | `GET /{_locale}/paypal/redirect-return/{token}/{nonce}` |
   | `sylius_paypal_shop_redirect_cancel` | `GET /{_locale}/paypal/redirect-cancel/{token}/{nonce}` |
   | `sylius_paypal_admin_order_payment_refund_late_capture` | refunds a `paypal_late_capture` from the admin |

   - The two redirect routes identify the payment by the order token and by a nonce minted per attempt and per
     route, never by the session.
   - A nonce that matches neither route answers `404`. A nonce of an earlier attempt of the same order redirects
     to the order page; on the return route it settles that attempt first.
   - The cancel route sends a payment PayPal completed after all to the thank-you page. It fails one the bank
     refused, with `sylius_paypal.something_went_wrong`, and flashes `sylius_paypal.payment_cancelled` only when
     it actually cancelled something.
   - Orders created for a redirect method point `return_url` and `cancel_url` at these routes instead of
     `sylius_shop_checkout_complete`.

1. **Deprecated routes**, removed in 3.0. Both keep working and emit a deprecation notice.

   | Route | Replacement |
   |---|---|
   | `sylius_paypal_shop_cancel_last_payment` | none |
   | `sylius_paypal_shop_update_paypal_order` | `sylius_paypal_order_shipping_callback` |

1. **JSON keys of the create and capture endpoints.** Every old key is still sent next to its replacement, with
   the same value and meaning. The old keys are deprecated and removed in 3.0.

   | Endpoint | New keys | Old key, still sent |
   |---|---|---|
   | `CreatePayPalOrderFromCartAction` | `id`, `orderId`, `status` | `orderID` (PayPal order id) |
   | `CreatePayPalOrderFromPaymentPageAction` | `id`, `orderId`, `status` | `order_id` (PayPal order id) |
   | `ProcessPayPalOrderAction` | `syliusOrderId`, `orderId`, `status`, `return_url` | `orderID` (**Sylius** order id) |
   | `CompletePayPalOrderFromPaymentPageAction` | `orderId`, `status` next to `return_url` | none |
   | `CreatePayPalOrderAction` | `orderId`, `payerActionUrl` | `orderID` |
   | `CompletePayPalOrderAction` | `orderId` | `orderID` |

   `ProcessPayPalOrderAction` omits `status` when the order has no payment left in the cart state.

1. **Request bodies and query parameters:**

   - `sylius_paypal_shop_create_paypal_order` reads an optional JSON body naming the source, such as
     `{"paymentSource": "google_pay"}`. An absent, empty or invalid body means `paypal`.
   - `sylius_paypal_shop_add_to_cart`, `sylius_paypal_shop_create_paypal_order_from_cart` and
     `sylius_paypal_shop_create_paypal_order_from_payment_page` read a `paymentSource` query parameter: `paypal`
     (the default) or `venmo` while it is enabled.
   - The Stimulus controllers post `{"error": …, "payPalOrderId": …}` to `sylius_paypal_shop_payment_error`. A
     body that is not a JSON object is still read as plain text, and then only logs and flashes.
   - `sylius_paypal_shop_add_to_cart` builds its form from `Sylius\Bundle\ShopBundle\Form\Type\AddToCartType`
     instead of `Sylius\Bundle\CoreBundle\Form\Type\Order\AddToCartType`, so the posted fields are named
     `sylius_shop_add_to_cart[...]` instead of `sylius_add_to_cart[...]`.

1. **New and changed status codes:**

   | Route | Answer |
   |---|---|
   | `sylius_paypal_shop_create_paypal_order_from_cart`, `…_from_payment_page`, `sylius_paypal_shop_complete_paypal_order_from_payment_page`, `sylius_paypal_shop_process_paypal_order` | `404` for an order that is neither the session's cart nor the order the session keeps as `sylius_order_id` after checkout (`OrderOwnershipVerifier`), e.g. from another session or a headless client |
   | `sylius_paypal_shop_add_to_cart`, `…_from_cart`, `…_from_payment_page` | `400` with the `sylius_paypal.payment_source_not_available` flash for a source that is unknown or disabled; `sylius_paypal_shop_add_to_cart` answers it before creating the cart |
   | `sylius_paypal_shop_add_to_cart` | `422 {"errors": [...]}` for an invalid form, instead of a redirect to the product page |
   | `sylius_paypal_shop_create_paypal_order` | `422` for a source the provider does not know, or one disabled on the channel (`venmo`, `google_pay`, `apple_pay`, `trustly`), with the same flash and before touching the payments; `409` when no payment awaits payment, or while a payment awaits the payer's bank |
   | `…_from_payment_page` | `409` when the order has no payment to pay with |
   | `sylius_paypal_shop_complete_paypal_order` | `409` when no payment is being processed; `422` when the posted PayPal order id is not the payment's |
   | `sylius_paypal_shop_complete_paypal_order_from_payment_page` | `409` when the order has no payment in `processing` (it used to answer `500`) |
   | `sylius_paypal_shop_process_paypal_order` | `422` when the posted `payPalOrderId` is not the payment's `paypal_order_id`, with a `return_url` to the checkout summary; an amount mismatch still answers `200` |
   | `sylius_paypal_shop_pay_with_paypal_form` | `404` for a missing payment or one of another gateway; redirect to the thank-you page for a completed payment and to the order page for one awaiting the payer's bank; `Cache-Control: no-store, private` and a `Permissions-Policy` delegating WebAuthn to `sylius_paypal.web_url` |
   | `sylius_paypal_webhook_refund_order` | `503` for an event it could not finish; `204` for an unknown order |
   | `PATCH /api/v2/admin/shipments/{id}/ship` | `400` for any field other than `trackingCode`, `carrier` and `carrierNameOther`, **for every order, whatever it was paid with**; extra fields that Sylius core ignores are now rejected |

1. **Flash changes:**

   - `sylius_paypal_shop_cancel_checkout_payment` flashes `success` / `sylius_paypal.payment_cancelled` instead of
     `error` / `sylius_paypal.something_went_wrong`.
   - `sylius_paypal_shop_cancel_order` flashes `sylius_paypal.order_cancelled` instead of the untranslated
     `sylius.pay_pal.order_cancelled`.

1. **The webhook endpoint.** `sylius_paypal_webhook_refund_order` keeps its name and path
   (`POST /paypal-webhook/api/`). It now points at `sylius_paypal.controller.webhook.paypal_webhook`
   (`PayPalWebhookAction`), so decorating `sylius_paypal.controller.webhook.refund_order` no longer affects it.

1. **The Admin API ship operation.** The plugin redefines `sylius_api_admin_shipment_patch_ship` in
   `config/api_platform/Shipment.xml`, with `Sylius\PayPalPlugin\PackageTracking\Command\ShipShipmentWithCarrier`,
   which extends Sylius' `ShipShipment`, as its input. An app that redefines the operation has to keep that
   input. `sylius_paypal.command_handler.ship_shipment_with_carrier` decorates
   `sylius_api.command_handler.checkout.ship_shipment`.

### Services and extension points

1. **New services you can decorate or replace:**

   | Service | Interface | Purpose |
   |---|---|---|
   | `sylius_paypal.factory.paypal_order` | `Factory\PayPalOrderFactoryInterface` | the whole `v2/checkout/orders` payload |
   | `sylius_paypal.factory.purchase_unit` | `Factory\PurchaseUnitFactoryInterface` | one purchase unit, for creating and patching an order |
   | `sylius_paypal.factory.paypal_item` | `Factory\PayPalItemFactoryInterface` | the order's line items |
   | `sylius_paypal.provider.experience_context` | `Provider\ExperienceContextProviderInterface` | `experience_context`; add a `brand_name` here |
   | `sylius_paypal.provider.paypal_payment_source` | `Provider\PayPalPaymentSourceProviderInterface` | the `payment_source` node per method; teach it your own method |
   | `sylius_paypal.provider.paypal_order_created_statuses` | `Provider\PayPalOrderCreatedStatusesProviderInterface` | statuses `CaptureAction` treats as created (`CREATED`, `PAYER_ACTION_REQUIRED`) |
   | `sylius_paypal.provider.shipping_callback_url` | `Provider\ShippingCallbackUrlProviderInterface` | the callback URL, or `null` (with a warning) when PayPal cannot reach it |
   | `sylius_paypal.resolver.shipping_options` | `Resolver\ShippingOptionsResolverInterface` | the options offered for an order and a partial address |
   | `sylius_paypal.factory.shipping_options` | `Factory\ShippingOptionsFactoryInterface` | which option is selected by default |
   | `sylius_paypal.factory.paypal_shipping_address` | `Factory\PayPalShippingAddressFactoryInterface` | PayPal's redacted address → Sylius address |
   | `sylius_paypal.factory.shipping_callback_response` | `Factory\ShippingCallbackResponseFactoryInterface` | the callback's answer; keep `amount.breakdown.shipping` equal to the selected option and `amount.value` equal to the breakdown sum |
   | `sylius_paypal.factory.express_order_address` | `Factory\ExpressOrderAddressFactoryInterface` | the address an approved express order is given |
   | `sylius_paypal.completer.express_order` | `Completer\PayPalExpressOrderCompleterInterface` | completes the payment and the order of an express checkout |
   | `sylius_paypal.provider.paypal_payment_page_context` | `Provider\PayPalPaymentPageContextProviderInterface` | everything the payment page renders |
   | `sylius_paypal.provider.web_sdk_configuration` | `Provider\WebSdkConfigurationProviderInterface` | the Web SDK script URL and instance config |
   | `sylius_paypal.provider.current_paypal_locale` | `Provider\CurrentPayPalLocaleProviderInterface` | the shop locale as PayPal expects it |
   | `sylius_paypal.verifier.three_d_secure` | `Verifier\ThreeDSecureVerifierInterface` | the 3D Secure policy |
   | `sylius_paypal.verifier.order_ownership` | `Verifier\OrderOwnershipVerifierInterface` | the ownership check of the order endpoints |
   | `sylius_paypal.verifier.webhook_request` | `Verifier\WebhookRequestVerifierInterface` | the webhook signature check |
   | `sylius_paypal.provider.webhook_url` | `Provider\WebhookUrlProviderInterface` | the webhook URL for registering, lookup and verification |
   | `sylius_paypal.checker.payer_action` | `Checker\PayerActionCheckerInterface` | whether a payment awaits the payer's bank; matches the two nonces |
   | `sylius_paypal.provider.nonce` | `Provider\NonceProviderInterface` | the payer action nonces |
   | `sylius_paypal.processor.payment_settlement` | `Processor\PaymentSettlementProcessorInterface` | settles a payment from its capture |
   | `sylius_paypal.processor.late_capture_refund` | `PayPalLateCaptureRefundProcessor` | refunds a `paypal_late_capture` |
   | `sylius_paypal.cache` | cache pool | the plugin's cache |

   All interfaces are under `Sylius\PayPalPlugin\`.

1. **Webhook processors.** `PayPalWebhookAction` verifies a request once and hands it to every
   `Sylius\PayPalPlugin\Processor\Webhook\WebhookProcessorInterface` that claims the event. The shipped ones are
   `sylius_paypal.processor.webhook.refund_order` and `sylius_paypal.processor.webhook.capture_payment`, passed
   as a list to `sylius_paypal.controller.webhook.paypal_webhook`. A replay re-runs every processor that claims
   the event, so `process()` has to be idempotent. A processor marks a failure as permanent, answered `204`, by
   throwing an exception implementing `Sylius\PayPalPlugin\Exception\PermanentWebhookFailureInterface`.

### Interfaces

1. **`CreateOrderApiInterface::create()`** gained three arguments, declared only as commented-out parameters, so
   an implementation with the 2.1 signature keeps loading. The plugin passes them anyway and `CreateOrderApi`
   declares them. An implementation without them creates every order as `paypal`. Add them before 3.0, which
   declares them on the interface.

   ```diff
    public function create(
        string $token,
        PaymentInterface $payment,
        string $referenceId,
   +    /* string $paymentSource = PayPalPaymentSourceProviderInterface::PAYPAL, */
   +    /* ?string $payerActionReturnNonce = null, */
   +    /* ?string $payerActionCancelNonce = null, */
    ): array;
   ```

1. **`PayPalFundingSourcesConfigurationProviderInterface`** gained `isVenmoEnabled()`, `isGooglePayEnabled()`,
   `isApplePayEnabled()` and `isTrustlyEnabled()`, each taking a `ChannelInterface`. Implement them if you
   implement the interface instead of decorating `sylius_paypal.provider.paypal_configuration`.

1. **`WebSdkConfigurationProviderInterface::getInstanceConfig()`** takes two optional arguments:

   ```diff
    public function getInstanceConfig(
        ChannelInterface $channel,
        string $pageType,
   +    array $components = self::DEFAULT_COMPONENTS,
   +    ?string $locale = null,
    ): array;
   ```

1. **`PayPalPaymentSourceProviderInterface`** gained the `TRUSTLY` and `BASE_EXPERIENCE_CONTEXT_KEYS`
   constants. Its `provide()` receives the payment the order is created for, and declares
   `InvalidPayerDataException` next to `UnsupportedPayPalPaymentSourceException`.

1. **`PayerActionCheckerInterface`** has `matchesPayerActionReturnNonce()` and `matchesPayerActionCancelNonce()`.

1. **`SettleablePaypalPaymentQueryInterface`** is new, with
   `getForSettlementByOrderId(): PaymentInterface`, which throws `PaymentNotFoundException` instead of
   answering `null`. `PaypalPaymentQuery` implements it next to `PaypalPaymentQueryInterface`, and both aliases
   point at it.

1. **`PaypalPaymentQueryInterface`** still declares `?PaymentInterface` on its three finders, while
   `PaypalPaymentQuery` narrowed them to `PaymentInterface`. The interface will be narrowed in 3.0; an
   implementation should drop the `?` before then.

1. **`PayPalOrderFactoryInterface::create()`** takes the payment source and the two payer action nonces as
   trailing optional arguments. A redirect order built without nonces throws.
   `PurchaseUnitFactoryInterface::create()` takes the merchant id as an optional third argument, which falls
   back to the payment method's `merchant_id`.

### Constructors

Unless stated otherwise, each new argument is appended, optional and nullable. Not passing it triggers a
deprecation, and it will be required in 3.0. If you instantiate, decorate or redefine one of these services
with an explicit argument list, add the new arguments.

The "Without it" column says what happens when a new argument is missing:

- **degrades**: the class keeps its 2.1 behaviour;
- **throws**: it raises a `\RuntimeException` when the argument is needed. Those arguments carry security or
  correctness the feature cannot work without.

| Class | New arguments | Without it |
|---|---|---|
| `Controller\CreatePayPalOrderFromCartAction` | `OrderOwnershipVerifierInterface`, `PayPalFundingSourcesConfigurationProviderInterface` | verifier: **throws** on every request; funding sources: degrades, `venmo` refused |
| `Controller\CreatePayPalOrderFromPaymentPageAction` | `OrderProcessorInterface` (`sylius.order_processing.order_payment_processor.checkout`), `ObjectManager`, `OrderOwnershipVerifierInterface`, `PayPalFundingSourcesConfigurationProviderInterface` | verifier: **throws**; processor and manager: degrades, the abandoned attempt is kept and the endpoint answers `409`; funding sources: `venmo` refused |
| `Controller\CompletePayPalOrderFromPaymentPageAction` | `OrderOwnershipVerifierInterface` | **throws** on every request |
| `Controller\ProcessPayPalOrderAction` | `UrlGeneratorInterface`, `PayPalExpressOrderCompleterInterface`, `OrderProcessorInterface`, `RepositoryInterface` (shipping methods), `ExpressOrderAddressFactoryInterface`, `OrderOwnershipVerifierInterface`, `UpdateOrderApiInterface` | verifier: **throws** on every request; router, completer, processor: **throw** when needed; shipping method repository: the wallet's method is not applied and the amount check fails; address factory: no region stored; update API: a lower total is a mismatch |
| `Controller\PayPalButtonsController` | `WebSdkConfigurationProviderInterface`, `PayPalFundingSourcesConfigurationProviderInterface`, `CurrentPayPalLocaleProviderInterface` | Web SDK and funding sources: **throw** on render; locale: built from the locale context |
| `Controller\PayWithPayPalFormAction` | `PayPalConfigurationProviderInterface`, `PayPalPaymentPageContextProviderInterface`, `UrlGeneratorInterface`, `PayerActionCheckerInterface`, `?string $webUrl` | context provider and router: **throw** on render; `$webUrl`: the `Permissions-Policy` allows both PayPal origins |
| `Controller\CompletePayPalOrderAction` | `CacheAuthorizeClientApiInterface`, `OrderDetailsApiInterface`, `ThreeDSecureVerifierInterface` | degrades, no 3D Secure check |
| `Controller\CreatePayPalOrderAction` | `PayPalPaymentSourceProviderInterface`, `PayPalFundingSourcesConfigurationProviderInterface` | only `paypal` accepted |
| `Controller\PayPalPaymentOnErrorAction` | `PaypalPaymentQueryInterface`, `StateMachineInterface`, `OrderProcessorInterface` (`sylius.order_processing.order_payment_processor.checkout`), `ObjectManager`, `PayerActionCheckerInterface` | degrades, only logs and flashes |
| `Controller\AddToCartAction` | `CartStorageInterface`, `PayPalFundingSourcesConfigurationProviderInterface` | degrades, `venmo` refused |
| `ApiPlatform\PayPalPayment` | `PayPalConfigurationProviderInterface` | degrades |
| `Api\CreateOrderApi` | `PayPalOrderFactoryInterface` | builds a default factory: no return and cancel URLs, no shipping callback |
| `Api\UpdateOrderApi` | `PurchaseUnitFactoryInterface` | builds a default factory |
| `Payum\Action\CaptureAction` | `PayPalOrderCreatedStatusesProviderInterface`, `NonceProviderInterface` | default statuses provider |
| `Payum\Action\CompleteOrderAction` | `LoggerInterface` | no deprecation |
| `Provider\PayPalItemDataProvider` | `PayPalItemFactoryInterface` | builds a factory without a router, so items carry no `url` |
| `Twig\PayPalExtension` | `PayPalFundingSourcesConfigurationProviderInterface`, `ChannelContextInterface`, `WebSdkConfigurationProviderInterface`, `PayerActionCheckerInterface`, `CurrentPayPalLocaleProviderInterface` | the new functions return `false`, `''` or `[]`; no locale resolved |
| `Manager\PaymentStateManager` | `PayerActionCheckerInterface` | default checker, no deprecation |
| `Provider\WebhookIdProvider`, `Registrar\SellerWebhookRegistrar` | `WebhookUrlProviderInterface` | default provider, no deprecation |
| `Controller\Webhook\RefundOrderAction` | `WebhookRequestVerifierInterface` | default verifier, no deprecation |
| `Repository\Query\PaypalPaymentQuery` | settleable states | has a default |
| `Console\Command\CompletePaidPaymentsCommand` | `PaymentSettlementProcessorInterface` | builds one from the deprecated arguments; given only the repository, settles nothing |

Further changes to existing constructors:

- `PayWithPayPalFormAction`: `AvailableCountriesProviderInterface`, `CacheAuthorizeClientApiInterface`,
  `IdentityApiInterface`, `LocaleProcessorInterface` and `PayPalConfigurationProviderInterface` are no longer
  used. Passing them is deprecated, and they will be removed in 3.0. The service definition passes `null` in
  their positions, so a definition copied from 2.1 lacks the two required arguments and throws on render.
- `CompletePaidPaymentsCommand`: the object manager, the authorize and order-details APIs and the state machine
  became optional. Passing them is deprecated, and they will be removed in 3.0.
- `CompletePayPalOrderAction`: the 2.1 service definition passed `sylius_paypal.api.authorize_client` and
  `sylius_paypal.api.complete_order` as unused fourth and fifth arguments. Those positions now take
  `CacheAuthorizeClientApiInterface` and `OrderDetailsApiInterface`, so a definition copied from 2.1 fails with
  a `TypeError`.
- `PayPalPaymentPageContextProvider` takes a required `PayPalFundingSourcesConfigurationProviderInterface`
  (`sylius_paypal.provider.paypal_configuration`). The class is new in 2.2.

### Models, payment details and the PayPal payload

1. **`PayPalGatewayConfig`.** `Sylius\PayPalPlugin\Model\PayPalGatewayConfig` reads a payment method's gateway
   config and holds its key names as public constants. `PayPalConfigurationProviderInterface` and
   `PayPalFundingSourcesConfigurationProviderInterface` keep their signatures and delegate to it.
   `trustly_enabled` comes from `RedirectPaymentSource::configurationKey()`.

   ```php
   $config = PayPalGatewayConfig::fromGatewayConfig($paymentMethod->getGatewayConfig());

   $config->clientId();                 // throws if the gateway does not carry one
   $config->hasClientId();
   $config->reportsSftpUsername();      // null when unset
   $config->isApplePayEnabled();
   $config->isCardThreeDSecureAlways();
   $config->isRedirectPaymentSourceEnabled(RedirectPaymentSource::Trustly);
   ```

   ```diff
   -$config = $paymentMethod->getGatewayConfig()->getConfig();
   -$merchantId = (string) $config['merchant_id'];
   +$merchantId = PayPalGatewayConfig::fromGatewayConfig($paymentMethod->getGatewayConfig())->merchantId();
   ```

1. **`PayPalOrder`** (not final):

   - It keeps `$order`, `$payPalPurchaseUnit` and `$intent`, and gains `?array $paymentSource = null` and
     `?string $processingInstruction = null`.
   - Given a payment source, `toArray()` sends it as `payment_source`, or omits the key when the array is empty.
     It never sends `application_context`.
   - Not passing a payment source is deprecated. The model then sends the 2.1
     `application_context.shipping_preference` derived from `$order`, which is read only on that path and will
     be removed in 3.0.
   - `toArray()` may emit `processing_instruction`.
   - `PayPalOrder::INTENT_CAPTURE` holds the capture intent, and `CreateOrderApi::PAYPAL_INTENT_CAPTURE` is an
     alias of it.

1. **`PayPalPurchaseUnit`** (not final) gained `?string $customId = null` and `bool $withItemTaxes = true`.
   `false` drops `tax` from every item, which `PayPalOrderFactory` does when the order declares the shipping
   callback.

1. **New models:**

   - `PayPalCapture` is a read-only view of `purchase_units[0].payments.captures[0]`, built with
     `PayPalCapture::fromPayPalOrder()` (or `null` when there is no capture). It carries the `STATUS_*` constants.
   - `PayPalShippingOptions` and `PayPalShippingOption` are what the shipping options resolver returns. Each
     option keeps its price in minor units and offers `TYPE_PICKUP`; the collection answers `isEmpty()` and
     `selected()`.

1. **New keys in `Payment::getDetails()`:**

   | Key | Meaning |
   |---|---|
   | `payment_source` | the source the buyer chose (`paypal`, `venmo`, `google_pay`, `apple_pay`, `trustly`, `card`) |
   | `payer_action_url` | the bank link of a redirect payment |
   | `payer_action_return_nonce`, `payer_action_cancel_nonce` | the nonces of the attempt's redirect routes; dropped on settlement |
   | `paypal_late_capture` | a capture PayPal completed for a cancelled or failed payment. The admin shows it with a Refund button that refunds it at PayPal without changing the payment state. Do not complete the order's new payment by hand for that money: it carries no `paypal_order_id`, so a refund in Sylius would not reach PayPal |
   | `captured_amount`, `captured_currency_code` | what the capture actually settled |

1. **The `v2/checkout/orders` payload:**

   - `payment_source.paypal.experience_context` replaces `application_context` on every flow. It carries
     `locale`, `shipping_preference`, `contact_preference`, `user_action`, `payment_method_preference`,
     `app_switch_preference` and identical `return_url`/`cancel_url`.
   - `shipping_preference` follows the flow:
     - a shortcut placement with no address sends `GET_FROM_FILE` and `UPDATE_CONTACT_INFO`;
     - an order with an address sends `SET_PROVIDED_ADDRESS` and `RETAIN_CONTACT_INFO`;
     - an order that is not shippable sends `NO_SHIPPING`.

     `order_update_callback_config` is sent only on the `GET_FROM_FILE` flow, when PayPal can reach the callback.
   - `brand_name` is not sent; PayPal shows the merchant account's business name.
   - Each item carries `category`: `DIGITAL_GOODS` for an order without shipping, `PHYSICAL_GOODS` otherwise. When
     resolvable it also carries `sku` (variant code), `description` and `url`.
   - `custom_id` carries the payment reference number, and `invoice_id` makes it unique per attempt.
   - Shipping addresses carry `admin_area_1`.
   - The payment source depends on the method:
     - Google Pay and cards send `attributes.verification.method`;
     - Venmo sends `payment_source.venmo`;
     - Trustly sends `name`, `country_code`, `email` and an `experience_context` limited to
       `BASE_EXPERIENCE_CONTEXT_KEYS`;
     - Apple Pay sends no `payment_source` at all.
   - `ThreeDSecureVerifier` reads `authentication_result` from any payment source, nested directly or under
     `card`.

### Webhooks, exceptions and error handling

1. The plugin subscribes to `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED`, `PAYMENT.CAPTURE.DECLINED` and
   `PAYMENT.CAPTURE.PENDING` next to `PAYMENT.CAPTURE.REFUNDED` (`WebhookApi::EVENT_TYPES`). Registering, looking
   up the id and verifying a signature all go through `WebhookUrlProviderInterface`, which honours
   `sylius_paypal.webhook_base_url`.
1. `Exception\OrderNotFoundException` extends `NotFoundHttpException` instead of `\Exception`. Left uncaught it
   renders as `404` instead of `500`, and `catch (HttpExceptionInterface)` or `catch (\RuntimeException)` now
   catches it.
1. `GenericApi::get()` throws `PayPalPluginException` for any status other than `200`, instead of returning
   PayPal's error document as data. `PayPalRefundDataProvider` throws `PayPalWrongDataException` for a response
   it cannot read, and `WebhookIdProvider` reports a failed lookup.
1. `Exception\InvalidPayerDataException` replaces four Webmozart assertions in `PayPalPaymentSourceProvider`.
   It is thrown when a redirect order lacks a billing address, an accepted country code, a payer name or an
   email.
1. `PayPalWrongDataException` implements `PermanentWebhookFailureInterface`. `PayPalPaymentMethodNotFoundException`
   and `PaymentNotFoundException` do not.
1. `CompleteOrderAction` returns early for a redirect payment source, which PayPal has already captured.
1. `CaptureAction` stores the `payer-action` link and the two nonces of a redirect payment.

### Other changes

1. The plugin prepends a `SyliusPayPalPluginPackageTracking` mapping to `doctrine.orm.mappings`, which lands in
   the default entity manager.
1. `PayPalSandboxPaymentMethodCreatorInterface::PARTNER_ATTRIBUTION_ID` changed from `sylius-ppcp4p-bn-code` to
   `Sylius_MP_PPCP`. It seeds only payment methods created through the sandbox flow; existing gateway configs
   keep their stored value.
1. `Sylius\PayPalPlugin\Provider\AvailableCountriesProvider`, which is not final, gained a public
   `provideForChannel(ChannelInterface): array`.

## Deprecations

Everything below will be removed or required in 3.0:

1. The routes `sylius_paypal_shop_cancel_last_payment` and `sylius_paypal_shop_update_paypal_order`.
1. The old JSON keys `orderID` and `order_id` of the create and capture endpoints.
1. Not passing a constructor argument added in 2.2 (see [Constructors](#constructors)).
1. Passing the five unused arguments of `PayWithPayPalFormAction`, and the four unused arguments of
   `CompletePaidPaymentsCommand`.
1. Not passing a `$paymentSource` to `PayPalOrder`, and its `$order` argument.
1. `CreateOrderApi::PAYPAL_INTENT_CAPTURE`, in favour of `PayPalOrder::INTENT_CAPTURE`.
1. `Controller\Webhook\RefundOrderAction` and the `sylius_paypal.controller.webhook.refund_order` service, in
   favour of `PayPalWebhookAction` and `RefundOrderWebhookProcessor`.
1. The three commented-out arguments of `CreateOrderApiInterface::create()` will be declared.
1. The `?PaymentInterface` return types of `PaypalPaymentQueryInterface` will be narrowed to `PaymentInterface`.

## Known limitations

1. If PayPal answers `confirmOrder()` with `PAYER_ACTION_REQUIRED` for Google Pay or Apple Pay, the payment is
   failed rather than captured. PayPal does not document that branch. It is reachable in the EEA.
1. Only `FindEligibleMethodsApi` and `PayPalCapture` format amounts with `AmountUtils`. The other places that
   build a `value` still assume two decimals, which PayPal refuses for HUF, JPY and TWD.
