# UPGRADE FROM 3.0 TO 3.1

1. #### The buyer's PayPal wallet or card can be vaulted for charges the merchant initiates later.

   Nothing is vaulted by default. To ask PayPal to vault the payment source of an order, for example one with a
   subscription, decorate or replace `sylius_paypal.resolver.vault_request`
   (`Sylius\PayPalPlugin\Resolver\VaultRequestResolverInterface`). The default `NullVaultRequestResolver` returns
   `null`; return a `Sylius\PayPalPlugin\Model\PayPalVaultRequest` instead:

   ```php
   use Sylius\PayPalPlugin\Model\PayPalVaultRequest;

   return new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID, 'Monthly coffee subscription');
   ```

   The PayPal order of such an order asks PayPal to store the payment source on success:

   - the PayPal wallet in `payment_source.paypal.attributes.vault`, with the usage pattern and the description
     (cut to 128 characters);
   - a card in `payment_source.card.attributes.vault` (`store_in_vault: ON_SUCCESS` only, without the usage pattern
     and the description), next to `payment_source.card.stored_credential` (`CUSTOMER`, `RECURRING`, `FIRST`).
     The card verification (`SCA_WHEN_REQUIRED`, or `SCA_ALWAYS` with `card_three_d_secure_always`) is unchanged.

   A vault is requested only when the merchant's PayPal account can vault: the gateway config has
   `vaulting_enabled: true`, or else a capability check with PayPal says so (see "The vaulting capability of the
   PayPal account is stored on the payment method and cached"). The decision is made by
   `sylius_paypal.provider.vault_request` (`Sylius\PayPalPlugin\Provider\PayPalVaultRequestProviderInterface`), whose
   `provide($order, $paymentMethod)` returns a `Sylius\PayPalPlugin\Model\PayPalVaultRequirement`: not required (no
   vault request, or not a PayPal payment method), met (with the vault request) or unmet (the merchant cannot vault).

   After the capture or the authorization, the vault PayPal returns is stored in the payment details: `vault_id`,
   `vault_status`, `vault_customer_id` and, for the PayPal wallet, `payer_email`, next to `payment_source` (`paypal` or
   `card`). Read the token with `Sylius\PayPalPlugin\Model\PayPalPaymentDetails::fromPayment($payment)->vaultId()`;
   `vault()` returns `null` when no `vault_status` is stored, as on a payment charged through `charge_vaulted`.

1. #### An order that needs vaulting can only be paid with the PayPal wallet or a card.

   When `sylius_paypal.resolver.vault_request` returns a vault request for an order:

   - the PayPal payment method is not offered at checkout when the merchant cannot vault
     (`sylius_paypal.resolver.payment_methods.vaulting_aware` decorates `sylius.resolver.payment_methods.channel_based`;
     check the priority if you decorate it too);
   - the payment page offers only the PayPal wallet and the card fields: Apple Pay, Google Pay, Pay Later, Venmo,
     PayPal messages and redirect payment sources are hidden, and its `sylius_paypal.shop.pay_with_paypal.content` hook
     gets a new `vaultRequired` variable. Pass it on if you override `@SyliusPayPalPlugin/pay_with_paypal.html.twig`.
     The shop API payment configuration follows it: `paymentSources` holds only `paypal` and, when enabled, `card`,
     `payLaterEnabled` is `false` and `messagingInstanceConfig` is `null`;
   - Pay Later messages are hidden in the cart and on the select payment step. `sylius_paypal_is_messaging_enabled()`
     takes the order as a new optional second argument; pass it if you override
     `@SyliusPayPalPlugin/shop/cart/index/content/form/sections/general/paypal_messaging.html.twig` or
     `@SyliusPayPalPlugin/shop/checkout/select_payment/content/form/select_payment/payment/choice/details/paypal_messaging.html.twig`;
   - Pay Later messages are hidden on the page of a product variant that needs vaulting.
     `sylius_paypal_is_messaging_enabled()` takes the product variant as a new optional third argument; pass it if you
     override `@SyliusPayPalPlugin/shop/product/show/content/info/summary/paypal_messaging.html.twig`. The decision is
     made by `sylius_paypal.provider.product_variant_vault_request`
     (`Sylius\PayPalPlugin\Provider\ProductVariantVaultRequestProviderInterface`), which asks
     `sylius_paypal.provider.vault_request` about a new, unsaved cart holding one unit of the variant;
   - a `capture` or `authorize` Payment Request creating the PayPal order fails when the merchant cannot vault or the
     payment source is neither `paypal` nor `card`;
   - the PayPal buttons on the cart and the select payment step render nothing when the merchant cannot vault, and show
     neither Venmo nor Pay Later otherwise. The product page keeps them while express checkout is enabled, as it cannot
     tell yet what the cart will hold;
   - `sylius_paypal_shop_create_paypal_order_from_cart` and the product page's add to cart answer `400` with the
     `sylius_paypal.vaulting_not_available` flash when the merchant cannot vault, and with the
     `sylius_paypal.paypal_wallet_required` flash for `paymentSource=venmo` or `paymentSource=paylater`. The product
     page does not save the cart it refuses. When express checkout is disabled on the payment method, both keep
     answering `400` with `sylius_paypal.payment_source_not_available` before any vaulting check, and so does the
     product page for a disabled Venmo or Pay Later.

   If you redefine `sylius_paypal.provider.paypal_payment_source`, `sylius_paypal.provider.payment_sdk_configuration`,
   `sylius_paypal.twig.extension.paypal` or the controllers and the Payment Request handler listed in "The following
   constructors have gained new optional arguments", pass them `sylius_paypal.provider.vault_request`: without it no
   vault is requested and nothing is restricted.

   The Pay Later button now sends `paymentSource=paylater`
   (`Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface::PAY_LATER`), which both endpoints accept when
   Pay Later is enabled and pay with the PayPal wallet. A `400` from these endpoints reloads the page without posting
   to the PayPal error URL; any other error is now thrown to the PayPal SDK. Rebuild the shop assets, and apply the
   same if you override `PaypalWebSdkController.js`.

   The card privacy notice on the payment page (`@SyliusPayPalPlugin/shop/pay_with_paypal/content/privacy_notice.html.twig`)
   is rendered only when the card fields are (`cardEnabled`).

   Add the `sylius_paypal.vaulting_not_available` and `sylius_paypal.paypal_wallet_required` flash translations to your
   own locales (`en`, `fr`, `nl` and `pl` are included).

1. #### A vaulted PayPal wallet or card is charged through a `charge_vaulted` Payment Request.

   Build a `Sylius\PayPalPlugin\Model\PayPalVaultedCredential` from the vaulted payment and dispatch it for a new
   payment, in the `new` state and with its PayPal payment method set, with
   `sylius_paypal.dispatcher.charge_vaulted_payment_request`
   (`Sylius\PayPalPlugin\Dispatcher\ChargeVaultedPaymentRequestDispatcherInterface`):

   ```php
   use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
   use Sylius\PayPalPlugin\Model\PayPalVaultedCredential;

   $credential = PayPalVaultedCredential::fromPaymentDetails(PayPalPaymentDetails::fromPayment($vaultedPayment));
   $paymentRequest = $chargeVaultedPaymentRequestDispatcher->dispatch($payment, $credential);
   ```

   `fromPaymentDetails()` returns `null` for a payment without a `vault_id` or with a payment source other than
   `paypal` and `card`. It takes the captured transaction id of the vaulted payment as
   `previous_transaction_reference`, which is sent to PayPal for a card only. The dispatcher throws a
   `\LogicException` for a payment that is not `new`. It reuses a `new` `charge_vaulted` Payment Request of the payment
   (setting the credential as its payload) or a `processing` one as it is, or creates one, and PayPal charges the payment source without the
   buyer (`stored_credential`: `MERCHANT`, `RECURRING`, `SUBSEQUENT`). The PayPal request id is derived from the
   Payment Request, so sending it again does not charge twice.

   The handler:

   - cancels the Payment Request with a `reason` and charges nothing when the payment is no longer `new`;
   - fails only the Payment Request when its payment method is not a PayPal one;
   - fails the payment and the Payment Request as `credential_invalid` when the payload names no credential;
   - completes the payment for a completed capture, and moves it to `processing` for a pending capture or a completed
     PayPal order without a capture;
   - otherwise fails the Payment Request, with `reason`, `failure` and, when PayPal refused the charge, `issues` in
     its response data. The payment fails too when PayPal refused the charge, created no capture, or declined or
     failed the capture.

   `failure` is a `Sylius\PayPalPlugin\Model\VaultedChargeFailure` value (`declined`, `credential_invalid`,
   `payer_action_required`, `unknown`), resolved by the decoratable `sylius_paypal.resolver.vaulted_charge_failure`.
   When PayPal answers `PERMISSION_DENIED`, the handler asks PayPal whether the token still exists and reports
   `credential_invalid` when it does not. A connection or PayPal server error is rethrown, so the retry is up to the
   transport of `sylius.payment_request.command_bus`.

   Vault tokens are read and deleted at PayPal with `sylius_paypal.api.payment_token`
   (`Sylius\PayPalPlugin\Api\PaymentTokenApiInterface::exists()` and `delete()`).

1. #### PayPal vault token webhooks are processed and announced on `sylius.event_bus`.

   `VAULT.PAYMENT-TOKEN.CREATED` and `VAULT.PAYMENT-TOKEN.DELETED`, which the webhook is subscribed to since 3.0 and
   which 3.0 ignored, are now processed:

   | Event type | What the plugin does |
   |---|---|
   | `VAULT.PAYMENT-TOKEN.CREATED` | stores the vault token on the payment of the PayPal order in `metadata.order_id`, unless it already has one |
   | `VAULT.PAYMENT-TOKEN.DELETED` | removes `vault_id` from every payment that carries the token and sets their `vault_status` to `DELETED` |

   Both are processed from the webhook payload instead of a resource read again from PayPal; a processor that wants
   the same implements `Sylius\PayPalPlugin\Processor\Webhook\PayloadResourceWebhookEventProcessorInterface`.

   A vault token stored from the capture or the authorization of a `capture` or `authorize` Payment Request, or from
   the `VAULT.PAYMENT-TOKEN.CREATED` webhook, is announced with `Sylius\PayPalPlugin\Event\PayPalPaymentTokenCreated`
   (`paymentId`, `vaultId`), and a deleted one with `Sylius\PayPalPlugin\Event\PayPalPaymentTokenDeleted` (`vaultId`).
   Both are dispatched on `sylius.event_bus` with `DispatchAfterCurrentBusStamp`, so the handlers run once the change
   is committed. That bus has no `doctrine_transaction` middleware: flush in the handler. The same token can in rare
   cases be announced twice, so make the handlers idempotent.

1. #### The vaulting capability of the PayPal account is stored on the payment method and cached.

   The gateway config gets `vaulting_enabled` when the payment method is created from the onboarding and when it is
   enabled outside of the sandbox. Payment methods created before have no flag, so the shop asks PayPal (see below).
   To store the flag and spare those calls, run:

   ```bash
   bin/console sylius-paypal:refresh-vaulting-capability
   ```

   Unless the flag is `true`, the shop asks PayPal whether the account can vault
   (`sylius_paypal.checker.vaulting_capability.cached`, reading the merchant integration status) and caches the answer
   in `sylius_paypal.cache`: a positive one for `sylius_paypal.vaulting_capability_check_cooldown` seconds (`3600` by
   default), a negative one or an error for `sylius_paypal.vaulting_capability_check_retry_cooldown` seconds (`300` by
   default). A shop request never writes the flag, and writing it does not clear the cached answer.

1. #### `PayPalClientInterface` has a new `delete()` method.

   ```diff
    // Sylius\PayPalPlugin\Client\PayPalClientInterface
   +/** @throws PayPalApiException */
   +public function delete(PaymentMethodInterface $paymentMethod, string $url): array;
   ```

   Implement it if you replaced `sylius_paypal.client.paypal`.

1. #### `PaypalPaymentQuery` finds the payments of a vault token.

   `Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQuery` implements the new
   `Sylius\PayPalPlugin\Repository\Query\VaultedPaypalPaymentQueryInterface::findByVaultId(string $vaultId): array`.
   `sylius_paypal.repository.query.paypal_payment` is passed where this interface is needed, so implement it if you
   replaced that service.

1. #### The following constructors have gained new optional arguments.

   ```diff
    // Sylius\PayPalPlugin\Controller\AddToCartAction
    // Sylius\PayPalPlugin\Controller\CreatePayPalOrderFromCartAction
    // Sylius\PayPalPlugin\Controller\PayPalButtonsController
    // Sylius\PayPalPlugin\CommandHandler\CreatePayPalOrderPaymentRequestHandler
    // Sylius\PayPalPlugin\Provider\PaymentSdkConfigurationProvider
    // Sylius\PayPalPlugin\Twig\PayPalExtension
    public function __construct(
        ...
   +    ?PayPalVaultRequestProviderInterface $vaultRequestProvider = null,
    )
   ```

   ```diff
    // Sylius\PayPalPlugin\Twig\PayPalExtension
    public function __construct(
        ...
        ?PayPalVaultRequestProviderInterface $vaultRequestProvider = null,
   +    ?ProductVariantVaultRequestProviderInterface $productVariantVaultRequestProvider = null,
    )
   ```

   ```diff
    // Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProvider
   +public function __construct(?PayPalVaultRequestProviderInterface $vaultRequestProvider = null)
   ```

   ```diff
    // Sylius\PayPalPlugin\CommandHandler\AuthorizePayPalOrderPaymentRequestHandler
    // Sylius\PayPalPlugin\CommandHandler\CapturePayPalOrderPaymentRequestHandler
    public function __construct(
        ...
   +    ?MessageBusInterface $eventBus = null,
    )
   ```

   ```diff
    // Sylius\PayPalPlugin\Resolver\WebhookEventPaymentResolver
    public function __construct(
        SettleablePaypalPaymentQueryInterface $paypalPaymentQuery,
   +    ?VaultedPaypalPaymentQueryInterface $vaultedPaypalPaymentQuery = null,
    )
   ```

   ```diff
    // Sylius\PayPalPlugin\Factory\PayPalItemFactory
    public function __construct(
        UrlGeneratorInterface $urlGenerator,
   +    ?RequestStack $requestStack = null,
    )
   ```

   Not passing them is deprecated since 3.1 and they will be required in 4.0. Without the vault request provider no
   vault is requested and nothing is restricted; without the event bus no `PayPalPaymentTokenCreated` is announced from
   a capture or an authorization; without the vaulted payment query the `VAULT.PAYMENT-TOKEN.DELETED` webhook finds no
   payment; without the request stack an item of a vaulted charge sent outside of a request links its product to the
   router's default host instead of the channel hostname in the order locale.
