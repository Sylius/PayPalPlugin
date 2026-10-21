# UPGRADE FROM 2.1 to 3.0

1. #### `PayPalSandboxPaymentMethodCreatorInterface::PARTNER_ATTRIBUTION_ID` has been removed.

   The BN code is not sandbox specific: it is sent as the `PayPal-Partner-Attribution-Id` header in production
   too, including the onboarding requests. Use `Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension::PARTNER_ATTRIBUTION_ID`
   or the `sylius_paypal.partner_attribution_id` parameter instead.

1. #### The PayPal payment method can only be created through the onboarding.

   Opening the admin create form for the `sylius_paypal` gateway (`/admin/payment-methods/new/sylius_paypal`)
   now always redirects to the payment methods list. In 2.1 the form was blocked only when a PayPal payment
   method already existed; now it is blocked as well when none exists, and the admin is pointed to the
   onboarding with the `sylius_paypal.create_paypal_payment_method_via_onboarding` flash. Create the payment
   method with the onboarding available on the payment methods list (or the sandbox onboarding in sandbox
   mode) instead of filling the credentials in by hand.

1. #### `PayPalPaymentMethodProvider` now looks the PayPal payment method up with a dedicated query.

   ```diff
    final readonly class PayPalPaymentMethodProvider
    {
        public function __construct(
   -        private PaymentMethodRepositoryInterface $paymentMethodRepository,
   +        private PayPalPaymentMethodQueryInterface $payPalPaymentMethodQuery,
        ) {
        }
   ```

   ```diff
    $services->set('sylius_paypal.provider.paypal_payment_method', PayPalPaymentMethodProvider::class)
        ->args([
   -        service('sylius.repository.payment_method'),
   +        service('sylius_paypal.repository.query.paypal_payment_method'),
        ]);
   ```

   `PayPalPaymentMethodProviderInterface` has gained the `exists(): bool` method.

1. #### The facilitator-based onboarding has been removed.

   The onboarding no longer goes through `paypal.sylius.com`. The following classes, interfaces and services
   have been removed without a replacement:

   | Removed class / interface | Removed service id / alias |
   |---|---|
   | `Sylius\PayPalPlugin\Onboarding\Initiator\OnboardingInitiator` | `sylius_paypal.onboarding.initiator` |
   | `Sylius\PayPalPlugin\Onboarding\Initiator\OnboardingInitiatorInterface` | alias of `sylius_paypal.onboarding.initiator` |
   | `Sylius\PayPalPlugin\Onboarding\Processor\BasicOnboardingProcessor` | `sylius_paypal.onboarding.processor.basic` |
   | `Sylius\PayPalPlugin\Onboarding\Processor\OnboardingProcessorInterface` | alias of `sylius_paypal.onboarding.processor.basic` |
   | `Sylius\PayPalPlugin\Factory\PayPalPaymentMethodNewResourceFactory` | `sylius_paypal.factory.paypal_payment_method_new_resource` |

   The `sylius_paypal.facilitator_url` parameter has been removed as well. The admin create form for the
   `sylius_paypal` gateway no longer fills the payment method in from an `onboarding_id` query parameter.

   The `Sylius\PayPalPlugin\Model\PayPalGatewayConfig::ONBOARDING_ID` constant has been removed. The `onboarding_id`
   key stored in the gateway config by the facilitator-based onboarding is no longer read and can be left in place.

   The `sylius_merchant_id` gateway config key, the merchant id assigned by the facilitator, is no longer written,
   mirrored between the sandbox and production credentials, or part of the PayPal configuration form. The following
   have been removed from `Sylius\PayPalPlugin\Model\PayPalGatewayConfig`:
   - the `SYLIUS_MERCHANT_ID` constant
   - the `syliusMerchantId()` and `hasSyliusMerchantId()` methods

   `sylius_merchant_id` has also been removed from `CredentialsManagerInterface::MIRRORED_KEYS`. The key already
   stored in the gateway config is no longer read and can be left in place.

1. #### `PayPalPaymentMethodEnabler` checks the seller's onboarding status directly with PayPal.

   ```diff
    public function __construct(
   -    private ClientInterface $client,
   -    private string $baseUrl,
   +    private AuthorizeClientApiInterface $authorizeClientApi,
   +    private MerchantOnboardingStatusApiInterface $merchantOnboardingStatusApi,
        private ObjectManager $paymentMethodManager,
        private SellerWebhookRegistrarInterface $sellerWebhookRegistrar,
   -    private RequestFactoryInterface $requestFactory,
   +    private PartnerCredentialsProviderInterface $partnerCredentialsProvider,
    ) {
   ```

   When the seller's onboarding is not complete, the thrown `PaymentMethodCouldNotBeEnabledException` carries
   the `OnboardingStatus`, available through `getOnboardingStatus()`.

   In sandbox mode only the credentials are verified, as there is no partner to ask for the onboarding status.

1. #### `PayPalPaymentMethodListener` no longer redirects to the facilitator.

   ```diff
    public function __construct(
   -    private OnboardingInitiatorInterface $onboardingInitiator,
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $flashBagOrRequestStack,
        private PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider,
   -    private bool $isSandbox = false,
    ) {
   ```

1. #### `EnableSellerAction` explains why the payment method could not be enabled.

   ```diff
    public function __construct(
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private PaymentMethodEnablerInterface $paymentMethodEnabler,
   +    private OnboardingStatusMessagesProviderInterface $onboardingStatusMessagesProvider,
    ) {
   ```

   Instead of the general `sylius_paypal.payment_not_enabled` message, the admin is shown the reasons provided
   by `OnboardingStatusMessagesProviderInterface` for the seller's onboarding status.

1. #### The sandbox credentials modal has been removed.

   The sandbox is connected through the same PayPal onboarding as production (`sylius_paypal:live_onboarding_modal`
   with `sandbox: true`). The following has been removed:
   - `Sylius\PayPalPlugin\Twig\Component\PayPalSandboxModalComponent` (`sylius_paypal:sandbox_onboarding_modal`)
     and the `@SyliusPayPalPlugin/admin/shared/components/paypal_sandbox_modal.html.twig` template
   - `Sylius\PayPalPlugin\Creator\PayPalSandboxPaymentMethodCreator` and its interface
     (`sylius_paypal.creator.sandbox_payment_method`)
   - `Sylius\PayPalPlugin\Form\Type\PayPalSandboxCredentialsType` and `Sylius\PayPalPlugin\Model\PayPalSandboxCredentials`
   - the `sylius_paypal.invalid_paypal_sandbox_credentials` flash message

1. #### The following signatures have gained new optional arguments.

   ```diff
    // Sylius\PayPalPlugin\Exception\PaymentMethodCouldNotBeEnabledException
   -public function __construct()
   +public function __construct(private readonly ?OnboardingStatus $onboardingStatus = null)
   ```

   ```diff
    // Sylius\PayPalPlugin\Exception\PayPalPluginException
   -public function __construct()
   +public function __construct(string $message = 'Could not load data from PayPal')
   ```

1. #### The sandbox mode is stored in the PayPal gateway config and switched in the admin panel.

   The `sylius_paypal.sandbox` configuration option and the `SYLIUS_PAYPAL_SANDBOX_ENABLED` environment variable
   have been removed. The mode is kept under the `sandbox` key of the PayPal gateway config and switched with
   the "Mode" field of the PayPal payment method form. Sandbox and production credentials are stored separately,
   under the `sandbox_credentials` and `production_credentials` keys, so switching does not require onboarding
   again.

   Run the migrations: the existing PayPal gateway config gets the `sandbox` key set from the
   `SYLIUS_PAYPAL_SANDBOX_ENABLED` environment variable available while migrating, or to `true` when it is not set,
   as `sylius_paypal.sandbox` defaulted to `true`. If you disabled the sandbox through the `sylius_paypal.sandbox`
   option only, either run the migrations with `SYLIUS_PAYPAL_SANDBOX_ENABLED=false` or switch the payment method
   to "Production" in the admin panel afterwards.

   The following parameters and services have been removed:

   | Removed | Replacement |
   |---|---|
   | `sylius_paypal.sandbox` parameter | the `sandbox` key of the gateway config of each PayPal payment method (`PayPalGatewayConfig::isSandbox()`) |
   | `sylius_paypal.api_base_url` parameter | `Sylius\PayPalPlugin\Provider\HostProviderInterface::getApiBaseUrlForMode()` (`sylius_paypal.provider.host`) |
   | `sylius_paypal.web_url` parameter | `Sylius\PayPalPlugin\Provider\HostProviderInterface::getWebUrlForMode()` (`sylius_paypal.provider.host`) |
   | `sylius_paypal.reports_sftp_host` parameter | `Sylius\PayPalPlugin\Provider\HostProviderInterface::getReportsSftpHostForMode()` (`sylius_paypal.provider.host`) |
   | `sylius_paypal.partner_js_url` parameter | `Sylius\PayPalPlugin\Provider\HostProviderInterface::getWebUrlForMode()` (used by the `sylius_paypal_partner_js_url()` Twig function) |
   | `sylius_paypal.client.sftp` service | `Sylius\PayPalPlugin\Factory\SftpClientFactoryInterface` (`sylius_paypal.factory.sftp_client`) |

   The following constructor signatures have changed:

   ```diff
    // Sylius\PayPalPlugin\Provider\WebhookIdProvider
    public function __construct(
        private GenericApiInterface $genericApi,
   -    private CacheAuthorizeClientApiInterface $authorizeClientApi,
   -    private UrlGeneratorInterface $urlGenerator,
   -    private string $baseUrl,
   -    private string $webhookBaseUrl = '',
   +    private HostProviderInterface $hostProvider,
   +    private WebhookUrlProviderInterface $webhookUrlProvider,
    ) {
   ```

   ```diff
    // Sylius\PayPalPlugin\Downloader\SftpPayoutsReportDownloader
   -public function __construct(private SFTP $sftp)
   +public function __construct(
   +    private SftpClientFactoryInterface $sftpClientFactory,
   +    private HostProviderInterface $hostProvider,
   +)
   ```

   ```diff
    // Sylius\PayPalPlugin\Form\Type\PayPalConfigurationType
   +public function __construct(
   +    private readonly CredentialsManagerInterface $credentialsManager,
   +    private readonly TranslatorInterface $translator,
   +)
   ```

   ```diff
    // Sylius\PayPalPlugin\Twig\PayPalExtension
    public function __construct(
   -    private readonly bool $sandbox,
   +    private readonly ModeSwitchViewFactoryInterface $modeSwitchViewFactory,
        private readonly ?PayPalFundingSourcesConfigurationProviderInterface $fundingSourcesConfigurationProvider = null,
        // ...
   +    ?HostProviderInterface $hostProvider = null,
    ) {
   ```

   The PayPal client, `AuthorizeClientApi`, `WebhookApi` and `WebhookSignatureVerifier` take the mode from the
   payment method they are called for, as described below.

1. #### PayPal API requests are retried by a PSR-18 decorator.

   `Sylius\PayPalPlugin\Client\RetryingHttpClient` (service `sylius_paypal.http_client.retrying`) wraps
   `sylius_paypal.http_client` and resends the very same request on network errors, `429`, `500`, `502`, `503` and
   `504`, with a jittered exponential backoff (250 ms doubled per attempt, capped at 2 s). A `Retry-After` of up to
   2 seconds is honoured; a longer one ends the retries. A request keeps its `PayPal-Request-Id` and body on every
   attempt. `PayPalClient`, `AuthorizeClientApi` and `WebhookSignatureVerifier` send through it.

   The `sylius_paypal.request_trials_limit` parameter (default `5`) has been replaced by
   `sylius_paypal.api.max_attempts` (default `3`), the total number of attempts. The waits go through
   `Symfony\Component\Clock\ClockInterface` (the `sylius_paypal.clock` alias of `clock`), so `symfony/clock` is now
   required.

   Network errors of any PSR-18 client are now retried; before, only Guzzle's `ConnectException` was.
   `sylius_paypal.http_client` is still an alias of `sylius.http_client`; point it to a scoped HTTP client to set
   timeouts for the PayPal API.

1. #### `PayPalClient` takes the payment method and resolves the access token itself.

   ```diff
    interface PayPalClientInterface
    {
   -    public function authorize(string $clientId, string $clientSecret): array;
   -
   -    public function get(string $url, string $token): array;
   +    public function get(PaymentMethodInterface $paymentMethod, string $url): array;

   -    public function post(string $url, string $token, ?array $data = null, array $extraHeaders = []): array;
   +    public function post(PaymentMethodInterface $paymentMethod, string $url, ?array $data = null, array $extraHeaders = []): array;

   -    public function patch(string $url, string $token, ?array $data = null): array;
   +    public function patch(PaymentMethodInterface $paymentMethod, string $url, ?array $data = null): array;
    }
   ```

   The client takes the token from `CacheAuthorizeClientApiInterface`. When PayPal answers `401`, it invalidates the
   cached token, fetches a fresh one and resends the request once. The request goes to the API of the payment
   method's mode (`HostProviderInterface::getApiBaseUrlForMode()`), the same one its token was issued by, and carries
   the `PayPal-Partner-Attribution-Id` of that payment method instead of the one of the channel in the request. The
   token request moved to `AuthorizeClientApi`.

   ```diff
    // Sylius\PayPalPlugin\Client\PayPalClient
    public function __construct(
   -    private ClientInterface $client,
   +    private ClientInterface $client, // sylius_paypal.http_client.retrying
        private LoggerInterface $logger,
        private UuidProviderInterface $uuidProvider,
   -    private PayPalConfigurationProviderInterface $payPalConfigurationProvider,
   -    private ChannelContextInterface $channelContext,
   +    private CacheAuthorizeClientApiInterface $authorizeClientApi,
   -    private string $baseUrl,
   +    private HostProviderInterface $hostProvider,
   -    private int $requestTrialsLimit,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private bool $loggingLevelIncreased = false,
    ) {
   ```

1. #### The API classes take the payment method instead of an access token.

   The first argument of the following methods changed from `string $token` to
   `Sylius\Component\Core\Model\PaymentMethodInterface $paymentMethod`:

   | Interface | Method |
   |---|---|
   | `Sylius\PayPalPlugin\Api\CompleteOrderApiInterface` | `complete()` |
   | `Sylius\PayPalPlugin\Api\CreateOrderApiInterface` | `create()` |
   | `Sylius\PayPalPlugin\Api\FindEligibleMethodsApiInterface` | `find()` |
   | `Sylius\PayPalPlugin\Api\GenericApiInterface` | `get()` |
   | `Sylius\PayPalPlugin\Api\IdentityApiInterface` | `generateToken()` |
   | `Sylius\PayPalPlugin\Api\OrderDetailsApiInterface` | `get()` |
   | `Sylius\PayPalPlugin\Api\RefundPaymentApiInterface` | `refund()` |
   | `Sylius\PayPalPlugin\Api\UpdateOrderAddressApiInterface` | `update()` |
   | `Sylius\PayPalPlugin\Api\UpdateOrderApiInterface` | `update()` |
   | `Sylius\PayPalPlugin\Api\UpdateWebhookApiInterface` | `update()` |
   | `Sylius\PayPalPlugin\Api\WebhookApiInterface` | `register()` |
   | `Sylius\PayPalPlugin\PackageTracking\Api\AddTrackingApiInterface` | `add()` |

   `WebhookSignatureVerifierInterface::verify()` takes the payment method as its third argument instead of the
   token:

   ```diff
   -public function verify(Request $request, string $webhookId, string $token): bool;
   +public function verify(Request $request, string $webhookId, PaymentMethodInterface $paymentMethod): bool;
   ```

   `WebhookApi` and `UpdateWebhookApi` send through `PayPalClientInterface`, their only constructor argument now.
   `WebhookSignatureVerifier` takes the token from `CacheAuthorizeClientApiInterface`, recovers from a `401` the same
   way the client does and asks the API of the payment method's mode.

   ```diff
    // Sylius\PayPalPlugin\Api\WebhookApi, Sylius\PayPalPlugin\Api\UpdateWebhookApi
    public function __construct(
   -    private ClientInterface $client,
   -    private string $baseUrl,
   -    private RequestFactoryInterface $requestFactory,
   -    private StreamFactoryInterface $streamFactory,
   +    private PayPalClientInterface $client,
    ) {
   ```

   ```diff
    // Sylius\PayPalPlugin\Api\WebhookSignatureVerifier
    public function __construct(
   -    private ClientInterface $client,
   +    private ClientInterface $client, // sylius_paypal.http_client.retrying
   +    private CacheAuthorizeClientApiInterface $authorizeClientApi,
   -    private string $baseUrl,
   +    private HostProviderInterface $hostProvider,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
   ```

1. #### `GenericApi` only sends the PayPal credentials to the PayPal API.

   `GenericApiInterface::get()` reads the absolute URLs PayPal links to, such as the `links` of a webhook event. It
   now sends the request through `PayPalClientInterface` only when the URL is an `https` URL of the API of the
   payment method's mode (`api.` or `api-m.`), without a port or credentials. Any other URL, including the API of the
   other mode, throws `Sylius\PayPalPlugin\Exception\UntrustedPayPalUrlException` before a request is sent. The
   exception implements `PermanentWebhookFailureInterface`, so a webhook event carrying such a link is not replayed.

   An error answered by PayPal throws a `PayPalApiException`, as for any other PayPal API call.

   ```diff
    // Sylius\PayPalPlugin\Api\GenericApi
    public function __construct(
   -    private ClientInterface $client,
   -    private RequestFactoryInterface $requestFactory,
   +    private PayPalClientInterface $client,
   +    private HostProviderInterface $hostProvider,
    ) {
   ```

1. #### Services that used to fetch the access token no longer depend on the token cache.

   The `CacheAuthorizeClientApiInterface $authorizeClientApi` argument has been removed from the constructors of:

   - `Sylius\PayPalPlugin\Creator\PayPalOrderCreator`
   - `Sylius\PayPalPlugin\PackageTracking\Processor\ShipmentTrackingProcessor`
   - `Sylius\PayPalPlugin\Processor\PaymentCaptureProcessor`
   - `Sylius\PayPalPlugin\Processor\PayPalPaymentSettlementProcessor`
   - `Sylius\PayPalPlugin\Provider\EligibleRedirectPaymentSourcesProvider`
   - `Sylius\PayPalPlugin\Provider\WebhookIdProvider`
   - `Sylius\PayPalPlugin\Registrar\SellerWebhookEventTypesRegistrar`
   - `Sylius\PayPalPlugin\Verifier\PaymentThreeDSecureVerifier`
   - `Sylius\PayPalPlugin\Verifier\WebhookRequestVerifier`

   `Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrar` has lost its first argument,
   `AuthorizeClientApiInterface $authorizeClientApi`.

1. #### `AuthorizeClientApi` takes the mode, returns an `AccessToken` and requests it itself.

   ```diff
    interface AuthorizeClientApiInterface
    {
   -    public function authorize(string $clientId, string $clientSecret): string;
   +    /** @throws PayPalApiException */
   +    public function authorize(string $clientId, string $clientSecret, bool $sandbox): AccessToken;
    }
   ```

   The token is requested from the API of the given mode. `Sylius\PayPalPlugin\Model\AccessToken` carries the token
   (`value()`) and the lifetime PayPal gives it (`expiresIn()`). A response without the token or its lifetime throws
   `PayPalApiAuthenticationException` instead of returning an empty string.

   ```diff
    // Sylius\PayPalPlugin\Api\AuthorizeClientApi
    public function __construct(
   -    private PayPalClientInterface $payPalClient,
   +    private ClientInterface $client, // sylius_paypal.http_client.retrying
   +    private RequestFactoryInterface $requestFactory,
   +    private StreamFactoryInterface $streamFactory,
   +    private HostProviderInterface $hostProvider,
    ) {
   ```

1. #### The access token is cached for the lifetime PayPal gives it, in one row per payment method.

   `CacheAuthorizeClientApi` keeps the token until five minutes before the `expires_in` PayPal returns, instead of a
   fixed hour, and refreshes the payment method's row in place instead of deleting and inserting it. For a payment
   method that is not saved yet, as during the onboarding, the token is fetched without being stored.

   `CacheAuthorizeClientApiInterface` has gained `invalidate(PaymentMethodInterface $paymentMethod): void`, which
   expires the cached token, and the constructor takes the clock:

   ```diff
    // Sylius\PayPalPlugin\Api\CacheAuthorizeClientApi
    public function __construct(
        private ObjectManager $payPalCredentialsManager,
        private ObjectRepository $payPalCredentialsRepository,
        private AuthorizeClientApiInterface $authorizeClientApi,
        private UuidProviderInterface $uuidProvider,
   +    private ClockInterface $clock,
    ) {
   ```

   ```diff
    interface PayPalCredentialsInterface
    {
        // ...
   -    public function isExpired(): bool;
   +    public function refresh(string $accessToken, \DateTime $creationTime, int $expiresIn): void;
   +
   +    public function expire(): void;
   +
   +    public function isExpired(\DateTimeInterface $now): bool;
    }
   ```

   The `sylius_paypal_plugin_pay_pal_credentials.payment_method_id` column is now unique. The
   `Version20261014120000` (MySQL) and `Version20261014120001` (PostgreSQL) migrations delete the cached tokens
   before adding the unique index; the tokens are fetched again on the next request.

1. #### A Payment Request captures its PayPal order and refunds its capture under a request id derived from the request.

   ```diff
    interface PaymentCaptureProcessorInterface
    {
   -    public function capture(PaymentInterface $payment): array;
   +    public function capture(PaymentInterface $payment, ?string $requestId = null): array;
    }
   ```

   ```diff
    interface CompleteOrderApiInterface
    {
   -    public function complete(string $token, string $orderId): array;
   +    public function complete(PaymentMethodInterface $paymentMethod, string $orderId, ?string $requestId = null): array;
    }
   ```

   ```diff
    interface RefundPaymentApiInterface
    {
        public function refund(
            PaymentMethodInterface $paymentMethod,
            string $paymentId,
            string $payPalAuthAssertion,
            string $invoiceNumber,
            string $amount,
            string $currencyCode,
   +        ?string $requestId = null,
        ): array;
    }
   ```

   `CapturePayPalOrderPaymentRequestHandler` passes `{payment request id}-capture` as the `PayPal-Request-Id`, so
   reprocessing the same Payment Request makes PayPal return the stored result of the first capture instead of
   capturing again. It locks the Payment and then the Payment Request (`PESSIMISTIC_WRITE`) before reading its
   state, so the buyer's return and the `CHECKOUT.ORDER.APPROVED` webhook capturing it at the same time handle it
   one after the other, in the same lock order as the webhook that creates a notify Payment Request of the Payment.
   `RefundPayPalPaymentRequestHandler` passes the payment request id, so reprocessing a
   refund Payment Request returns the first refund instead of refunding again. Without a request id, a random one
   is used, as before.

1. #### Failed PayPal API calls throw typed exceptions instead of returning the error body.

   `PayPalClientInterface`, `AuthorizeClientApiInterface` and every API class built on them throw a
   `Sylius\PayPalPlugin\Exception\PayPalApiException` when PayPal answers outside `2xx`, after the retries and the
   access token refresh, or cannot be reached. They no longer return PayPal's error body as a result.

   | Exception | Thrown when |
   |---|---|
   | `PayPalApiConnectionException` | PayPal could not be reached |
   | `PayPalApiAuthenticationException` | PayPal answered `401`, or the token endpoint answered without a token |
   | `PayPalApiNotFoundException` | PayPal answered `404` |
   | `PayPalApiServerException` | PayPal answered `429` or `5xx` |
   | `PayPalApiRequestException` | PayPal answered any other status, such as `400`, `403` or `422` |

   The exception carries the request method and path, the status, PayPal's error name and message, the issues of
   its `details` (`getIssues()`, `hasIssue()`) with their descriptions (`getIssueDescriptions()`) and the
   `PayPal-Debug-Id` (`getDebugId()`). `getDescription()` puts the issue descriptions in place of PayPal's general
   message when PayPal gives them.

   The following exceptions have been removed:

   | Removed | Replacement |
   |---|---|
   | `Sylius\PayPalPlugin\Exception\PayPalApiTimeoutException` | `PayPalApiConnectionException` |
   | `Sylius\PayPalPlugin\Exception\PayPalAuthorizationException` | `PayPalApiAuthenticationException` |
   | `Sylius\PayPalPlugin\Exception\PayPalOrderUpdateException` | none, it was never thrown |

   The plugin's own callers handle the new exceptions as follows:

   - A refund PayPal refuses, of a payment or of its late capture, fails the refund Payment Request with PayPal's
     reason, shown to the admin, instead of passing as a success. A capture PayPal reports as fully refunded
     (`CAPTURE_FULLY_REFUNDED`) counts as refunded.
   - A capture lost to a `5xx` or a network error, or refused as already captured, unapproved or awaiting the payer,
     still reads the PayPal order, which decides the outcome as before. Any other refusal fails the Payment Request;
     when PayPal declines the instrument (`INSTRUMENT_DECLINED`), the reason asks for another payment method.
   - A capture whose amount or shipping address PayPal refuses to update, or whose order cannot be read for the
     3D Secure check, fails the Payment Request instead of capturing anyway.
   - A status Payment Request whose PayPal order cannot be read fails instead of completing.
   - A PayPal order PayPal refuses to create fails its Payment Request.
   - A tracking PayPal accepts without returning its id is marked as failed without being retried.

1. #### Every PayPal API call is logged with its request, response and debug id.

   `Sylius\PayPalPlugin\Client\LoggingHttpClient` (services `sylius_paypal.http_client.logging` and
   `sylius_paypal.psr18_client.onboarding.logging`) logs every attempt of a PayPal API call, the onboarding ones
   included, on the `paypal` channel: the method, URL, status and `PayPal-Debug-Id` in the message, the request and
   response headers and bodies in the context. A successful call is logged as `info`, a call PayPal rejected or that
   never reached it as `error`. Credentials (authorization headers, tokens, client credentials) and the payer's
   personal data (name, contact details, address, card data) are masked.

   PayPal asks to keep the logs of failed calls for at least two weeks, as it purges its own after that. Route the
   `paypal` channel accordingly, for example:

   ```yaml
   monolog:
       handlers:
           paypal_failures:
               type: rotating_file
               path: '%kernel.logs_dir%/paypal_failures.log'
               level: error
               max_files: 14
               channels: [paypal]
   ```

1. #### PayPal webhook events are stored, so a duplicate delivery has one effect.

   Run the migrations to add the `sylius_paypal_plugin_webhook_event` table:

   ```bash
   bin/console doctrine:migrations:migrate
   ```

   It keeps the id of each received webhook event per payment method for
   `sylius_paypal.webhook_event_retention_days` days (`30` by default). Schedule
   `bin/console sylius-paypal:prune-webhook-events [--days=30]` to remove older ones.

1. #### Express checkout from the cart and the product page captures through a Payment Request.

   When the buyer approves in the wallet, `Sylius\PayPalPlugin\Controller\CompleteExpressCheckoutAction`
   (`sylius_paypal_shop_complete_express_checkout`, `POST /{_locale}/paypal/express-checkout/complete`) places the
   order with `Sylius\PayPalPlugin\Completer\ExpressCheckoutCompleter` and only then captures the PayPal order through
   a `capture` Payment Request, dispatched by `Sylius\PayPalPlugin\Dispatcher\ApprovedPayPalOrderPaymentRequestDispatcher`.
   The capture, its settlement and a declined or pending capture are handled as on the payment page. The response's
   `return_url` leads to the Payment Request (`/payment-request/pay/{hash}`), which ends on the thank-you page, or on
   the order to pay again when PayPal did not capture. A total that grew since the approval still returns the buyer
   to the unplaced checkout. A PayPal order created with the `AUTHORIZE` intent, by a payment method that authorizes
   (`use_authorize`), gets an `authorize` Payment Request instead, which leaves the payment `authorized`; the intent is
   stored in the payment details, so changing the capture strategy after the PayPal order was created does not matter.

   The request body (`orderId`, `payPalOrderId`) is unchanged; the response carries only `return_url`.

   The following have been removed:

   | Removed | Replacement |
   |---|---|
   | `Sylius\PayPalPlugin\Controller\ProcessPayPalOrderAction` (`sylius_paypal.controller.process_paypal_order`) | `Sylius\PayPalPlugin\Controller\CompleteExpressCheckoutAction` (`sylius_paypal.controller.complete_express_checkout`) |
   | route `sylius_paypal_shop_process_paypal_order` (`/process-pay-pal-order/`) | route `sylius_paypal_shop_complete_express_checkout` |
   | `Sylius\PayPalPlugin\Completer\PayPalExpressOrderCompleter` and its interface (`sylius_paypal.completer.express_order`) | `Sylius\PayPalPlugin\Completer\ExpressCheckoutCompleter` and its interface (`sylius_paypal.completer.express_checkout`) |
   | `processPayPalOrderUrl` in the context of `@SyliusPayPalPlugin/pay_from_cart_page.html.twig` and `@SyliusPayPalPlugin/pay_from_product_page.html.twig` | `completeExpressCheckoutUrl` |

1. #### The PayPal buttons on the select payment step complete the checkout like the cart buttons.

   The buttons rendered by `@SyliusPayPalPlugin/pay_from_payment_page.html.twig` create the PayPal order with
   `sylius_paypal_shop_create_paypal_order_from_cart` and complete the checkout with
   `sylius_paypal_shop_complete_express_checkout`, so the order is placed when the buyer approves and its PayPal order
   is captured through a `capture` Payment Request. Creating the PayPal order no longer selects the payment or moves
   the payment to `processing`, and closing the wallet only shows a flash message (`sylius_paypal_shop_cancel_order`).

   The context of `@SyliusPayPalPlugin/pay_from_payment_page.html.twig` has changed:

   | Removed | Replacement |
   |---|---|
   | `createPayPalOrderFromPaymentPageUrl` | `createPayPalOrderFromCartUrl` |
   | `completePayPalOrderFromPaymentPageUrl` | `completeExpressCheckoutUrl` |
   | `cancelPayPalPaymentUrl` | `path('sylius_paypal_shop_cancel_order')` in the template |

   The following have been removed:

   | Removed | Replacement |
   |---|---|
   | `Sylius\PayPalPlugin\Controller\CreatePayPalOrderFromPaymentPageAction` (`sylius_paypal.controller.create_paypal_order_from_payment_page`), route `sylius_paypal_shop_create_paypal_order_from_payment_page` (`/pay-pal-order-payment-page/{id}/create`) | `Sylius\PayPalPlugin\Controller\CreatePayPalOrderFromCartAction`, route `sylius_paypal_shop_create_paypal_order_from_cart` |
   | `Sylius\PayPalPlugin\Controller\CompletePayPalOrderFromPaymentPageAction` (`sylius_paypal.controller.complete_paypal_order_from_payment_page`), route `sylius_paypal_shop_complete_paypal_order_from_payment_page` (`/pay-pal-order-payment-page/{id}/complete`) | `Sylius\PayPalPlugin\Controller\CompleteExpressCheckoutAction`, route `sylius_paypal_shop_complete_express_checkout` |

1. #### Completing the checkout no longer captures a PayPal payment.

   Every PayPal order is now captured through a `capture` Payment Request after the order is placed, so nothing
   leaves a PayPal payment `processing` for checkout completion to capture. The following have been removed:

   | Removed | Replacement |
   |---|---|
   | `Sylius\PayPalPlugin\EventListener\Workflow\CompletePayPalOrderListener` (`sylius_paypal.listener.workflow.complete_paypal_order`, on `workflow.sylius_order_checkout.completed.complete`) | none |
   | the `complete_paypal_order` callback of the `sylius_order_checkout` winzou state machine | none |
   | `Sylius\PayPalPlugin\Processor\PayPalOrderCompleteProcessor` (`sylius_paypal.processor.paypal_order_complete`) | `Sylius\PayPalPlugin\CommandHandler\CapturePayPalOrderPaymentRequestHandler` |
   | `Sylius\PayPalPlugin\Manager\PaymentStateManager` and its interface (`sylius_paypal.manager.payment_state`) | `Sylius\PayPalPlugin\CommandHandler\CapturePayPalOrderPaymentRequestHandler` |
   | `Sylius\PayPalPlugin\Processor\PayPalPaymentCompleteProcessor` and `Sylius\PayPalPlugin\Processor\PaymentCompleteProcessorInterface` (`sylius_paypal.processor.payment_complete`) | `Sylius\PayPalPlugin\Processor\PaymentCaptureProcessor` |
   | `Sylius\PayPalPlugin\Processor\OrderPaymentProcessor` (`sylius_paypal.order_processing.order_payment_processor.checkout`, decorating `sylius.order_processing.order_payment_processor.checkout`) | none, a cart no longer holds a `processing` PayPal payment |
   | `Sylius\PayPalPlugin\Controller\UpdatePayPalOrderAction` (`sylius_paypal.controller.update_paypal_order`), route `sylius_paypal_shop_update_paypal_order` (`/update-pay-pal-order`) | none, the shipping callback (`sylius_paypal_order_shipping_callback`) updates the PayPal order while the buyer is in the wallet |

   The `sylius_paypal.repository.query.pay_pal_payment.updatable_states` parameter is now `[cart]`: only the payment of
   an order that is not placed yet has its PayPal order updated.

1. #### A PayPal payment is refunded through a refund Payment Request.

   The admin refunds a PayPal payment with the PayPal "Refund" button on the order page
   (`sylius_paypal_admin_order_payment_refund`, `PUT /admin/orders/{orderId}/payments/{id}/refund-paypal-payment`) or
   through the admin API (`PATCH /api/v2/admin/payments/{id}/paypal-refund`). Both create a `refund` Payment Request,
   or reuse the open one, and dispatch it with `RefundPayPalPaymentRequestDispatcherInterface`. The payment
   becomes `refunded` only once PayPal confirms the refund: `RefundPayPalPaymentRequestHandler` stores the
   confirmation in the payment details (`refunded`, `refund_id`) and applies the `refund` transition, a pending
   refund keeps the Payment Request `processing` until the `PAYMENT.CAPTURE.REFUNDED` webhook of that refund confirms
   it, and a refund PayPal rejects fails the Payment Request with the reason while the payment stays `completed`.

   The late capture is refunded the same way, through a `refund_late_capture` Payment Request dispatched with
   `RefundPayPalLateCapturePaymentRequestDispatcherInterface`. `PayPalLateCaptureRefundConfirmationProcessorInterface`
   records the confirmed refund in the `paypal_late_capture` payment details and leaves the payment state alone, a
   pending refund keeps the Payment Request `processing` until the `PAYMENT.CAPTURE.REFUNDED` webhook of that refund
   confirms it:

   ```diff
    // Sylius\PayPalPlugin\Controller\RefundPayPalLateCaptureAction
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
   -    private PaymentRefundProcessorInterface $lateCaptureRefundProcessor,
   +    private RefundPayPalLateCapturePaymentRequestDispatcherInterface $lateCaptureRefundPaymentRequestDispatcher,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $router,
    ) {
   ```

   The `refund` transition of a PayPal payment that PayPal has not confirmed as refunded is blocked by
   `PaymentRefundGuardListener` (on `workflow.sylius_payment.guard.refund`) or the `paypal_refund_confirmed` guard
   of the `sylius_payment` winzou state machine, so the core "Refund" button is hidden for such a payment and
   `PATCH /api/v2/admin/payments/{id}/refund` answers `422`.

   The following have been removed:

   | Removed | Replacement |
   |---|---|
   | `Sylius\PayPalPlugin\EventListener\Workflow\RefundPaymentListener` (`sylius_paypal.listener.workflow.refund_payment`, on `workflow.sylius_payment.transition.refund`) | `Sylius\PayPalPlugin\EventListener\Workflow\PaymentRefundGuardListener` (`sylius_paypal.listener.workflow.payment_refund_guard`) |
   | the `refund_paypal_order` callback of the `sylius_payment` winzou state machine | the `paypal_refund_confirmed` guard |
   | `Sylius\PayPalPlugin\Processor\PayPalPaymentRefundProcessor` (`sylius_paypal.processor.payment_refund`) | `Sylius\PayPalPlugin\CommandHandler\RefundPayPalPaymentRequestHandler` |
   | `Sylius\PayPalPlugin\Processor\UiPayPalPaymentRefundProcessor` (`sylius_paypal.processor.ui_paypal_payment_refund`) | `Sylius\PayPalPlugin\Controller\RefundPayPalPaymentAction` |
   | `Sylius\PayPalPlugin\Processor\PayPalLateCaptureRefundProcessor` (`sylius_paypal.processor.late_capture_refund`) | `Sylius\PayPalPlugin\CommandHandler\RefundPayPalLateCapturePaymentRequestHandler` |
   | `Sylius\PayPalPlugin\Processor\PaymentRefundProcessorInterface` | none |
   | `Sylius\PayPalPlugin\Exception\PayPalOrderRefundException` | the reason of the failed refund Payment Request |

1. #### PayPal webhooks are accepted on `/payment-methods/{code}`.

   Sylius' `sylius_payment_method_notify` route accepts PayPal webhooks for the payment method in the URL.
   `sylius_paypal.provider.notify_payment` verifies the signature with that method's webhook and finds the
   payment by the Payment Request hash PayPal was given as `custom_id`, then by the PayPal order or capture id.
   A signature it cannot verify is answered `404`, an event about no payment of that method `204`.
   `NotifyPaymentRequestHandler` changes nothing for an event it has already received, reads the event's
   resource again from PayPal and hands it to the `Sylius\PayPalPlugin\Processor\Webhook\WebhookEventProcessorInterface`
   tagged `sylius_paypal.webhook_event_processor` with that `event_type`; an event without one is recorded as
   `ignored`. The merchant events PayPal sends to this endpoint:

   | Event | Effect |
   |---|---|
   | `PAYMENT.CAPTURE.COMPLETED`, `.DENIED`, `.DECLINED`, `.PENDING`, `CHECKOUT.ORDER.COMPLETED` | settles the payment from its capture, an authorized payment too once its authorization has been captured, from the admin panel or at PayPal |
   | `PAYMENT.CAPTURE.REFUNDED` | refunds the payment, or marks its `paypal_late_capture` refunded, and completes the refund Payment Request waiting for that refund |
   | `PAYMENT.CAPTURE.REVERSED` | refunds the payment, or marks its `paypal_late_capture` refunded, without completing a refund Payment Request still waiting for PayPal |
   | `CHECKOUT.ORDER.APPROVED` | captures, or authorizes when the payment method authorizes, the order of a payment request still awaiting it; a capture or an authorization PayPal refuses stays on the failed payment request with its reason |
   | `CHECKOUT.ORDER.PROCESSED` | ignored; the capture events settle the payment |
   | `PAYMENT.AUTHORIZATION.CREATED` | authorizes the payment whose authorization PayPal created, when the payer never came back to the shop |
   | `PAYMENT.AUTHORIZATION.VOIDED` | cancels the authorized payment whose authorization was voided outside the shop, without voiding it again |

   ```diff
    // Sylius\PayPalPlugin\Verifier\WebhookRequestVerifierInterface
   -public function verify(Request $request): bool;
   +public function verify(Request $request, PaymentMethodInterface $paymentMethod): bool;
   ```

   `SettleablePaypalPaymentQueryInterface` has gained `getForSettlementByCaptureId(): PaymentInterface` and
   `getByPaymentRequestHash(): PaymentInterface`. `WebhookEventPaymentResolver` finds the payment of the Payment Request
   an event names through the latter, without loading the Payment Request, so the webhook does not rewrite (and lock)
   the Payment Request a buyer may be capturing at the same time.

1. #### PayPal webhooks are registered on the `/payment-methods/{code}` endpoint of their payment method.

   The onboarding, the payment method enabler and `sylius-paypal:register-webhook-event-types` register the webhook
   of a PayPal payment method on its `sylius_payment_method_notify` URL instead of `/paypal-webhook/api/`, subscribed
   to `CHECKOUT.ORDER.APPROVED`, `CHECKOUT.ORDER.COMPLETED`, `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED`,
   `PAYMENT.CAPTURE.DECLINED`, `PAYMENT.CAPTURE.PENDING`, `PAYMENT.CAPTURE.REFUNDED`, `PAYMENT.CAPTURE.REVERSED`,
   `PAYMENT.AUTHORIZATION.CREATED` and `PAYMENT.AUTHORIZATION.VOIDED`.

   `/paypal-webhook/api/` no longer exists, so a webhook still registered on it has to be moved:

   1. Make the console generate the public URL of the shop: set `SYLIUS_PAYPAL_WEBHOOK_BASE_URL`
      (`sylius_paypal.webhook_base_url`) or give the channels of the PayPal payment method their hostname. Without
      either, the command builds the URL from the console request context (`framework.router.default_uri`,
      `localhost` by default), which PayPal rejects.
   2. Run `bin/console sylius-paypal:register-webhook-event-types` as part of the deployment, right when the new
      release goes live; with an atomic deployment, from the new release before switching to it. For every PayPal
      payment method it finds the webhook registered on its notify URL or on `/paypal-webhook/api/`, points it at
      `/payment-methods/{code}` through a PATCH, so its id stays the same, subscribes it to the events above and
      stores its id. It registers a new webhook only when it finds none, so it can be run again.
   3. Check that it reports `Updated webhook <id> of "<code>" to point at <url>.` (or `Registered webhook <id> of
      "<code>" at <url>.` for a new one) with the public URL of the shop for every PayPal payment method and exits
      with `0`. A webhook it could not update stays where it was: fix the URL and run it again.

   `SellerWebhookEventTypesRegistrarInterface::register()` returns the `Sylius\PayPalPlugin\Model\WebhookRegistration`
   it made: the webhook id, its URL and whether it was registered anew.

   Until the command runs, PayPal gets a `404` from `/paypal-webhook/api/` for every event. A webhook moved before
   the new release is live gets an error from `/payment-methods/{code}` until it is, as the old release has no PayPal
   notify payment provider. PayPal delivers a failed event again for up to 3 days, so nothing is lost when the
   deployment and the command happen close together; a webhook left on `/paypal-webhook/api/` keeps failing and
   PayPal may disable it.

   Payment methods that share PayPal credentials used to share one webhook: the first of them takes it over and the
   others get their own. PayPal sends every event to each of them, and a payment method answers `204` to an event
   about a payment of another one.

   `WebhookUrlProviderInterface::LEGACY_PATH` and `provideLegacy()` are only there for the command to find a webhook
   still registered on `/paypal-webhook/api/`; they will be removed in 4.0.

   The webhook URL starts with `sylius_paypal.webhook_base_url` or, when it is empty, with the hostname of the first
   channel of the payment method that has one, so the command run from the console does not register `localhost`;
   only without either does it fall back to the request context.

   `WebhookIdProvider` looks the webhook up by the URL of the payment method, then by `/paypal-webhook/api/`.
   It is the `sylius_paypal.provider.webhook_id.lookup` service; `sylius_paypal.provider.webhook_id` is the
   `PersistingWebhookIdProvider` wrapping it instead of decorating it.
   `SellerWebhookEventTypesRegistrar` gets it from `sylius_paypal.provider.webhook_id.lookup`, which asks PayPal
   every time, so the refresh cooldown of `sylius_paypal.provider.webhook_id` cannot make it register a second webhook,
   and stores the id of the webhook it updates or registers in the gateway config through
   `Sylius\PayPalPlugin\Updater\WebhookIdUpdaterInterface` (`sylius_paypal.updater.webhook_id`), which
   `PersistingWebhookIdProvider` now takes instead of the object manager.

   ```diff
    // Sylius\PayPalPlugin\Provider\WebhookUrlProviderInterface
   -public const ROUTE = 'sylius_paypal_webhook_refund_order';
   +public const ROUTE = 'sylius_payment_method_notify';
   +public const LEGACY_PATH = '/paypal-webhook/api/';

   -public function provide(): string;
   +public function provide(PaymentMethodInterface $paymentMethod): string;
   +public function provideLegacy(PaymentMethodInterface $paymentMethod): string;
   ```

   ```diff
    // Sylius\PayPalPlugin\Api\UpdateWebhookApiInterface
   -public function updateEventTypes(PaymentMethodInterface $paymentMethod, string $webhookId, array $eventTypes): array;
   +public function update(PaymentMethodInterface $paymentMethod, string $webhookId, string $webhookUrl, array $eventTypes): array;
   ```

   The `sylius_paypal_webhook_refund_order` route (`/paypal-webhook/api/`) and the following have been removed:

   | Removed | Replacement |
   |---|---|
   | `Sylius\PayPalPlugin\Controller\Webhook\PayPalWebhookAction` (`sylius_paypal.controller.webhook.paypal_webhook`) | `Sylius\PayPalPlugin\Controller\Webhook\PaymentMethodNotifyAction` |
   | `Sylius\PayPalPlugin\Controller\Webhook\RefundOrderAction` (`sylius_paypal.controller.webhook.refund_order`) | `Sylius\PayPalPlugin\Controller\Webhook\PaymentMethodNotifyAction` |
   | `Sylius\PayPalPlugin\Processor\Webhook\RefundOrderWebhookProcessor` (`sylius_paypal.processor.webhook.refund_order`) | `Sylius\PayPalPlugin\Processor\Webhook\RefundPaymentWebhookEventProcessor` |
   | `Sylius\PayPalPlugin\Processor\Webhook\CapturePaymentWebhookProcessor` (`sylius_paypal.processor.webhook.capture_payment`) | `Sylius\PayPalPlugin\Processor\Webhook\SettlePaymentWebhookEventProcessor` |
   | `Sylius\PayPalPlugin\Processor\Webhook\WebhookProcessorInterface` | `Sylius\PayPalPlugin\Processor\Webhook\WebhookEventProcessorInterface` |
   | `Sylius\PayPalPlugin\Provider\PayPalRefundDataProvider` and its interface (`sylius_paypal.provider.paypal_refund_data`) | the event resource `NotifyPaymentRequestHandler` reads again from PayPal |
   | `PaypalPaymentQueryInterface::getForRefundingByOrderId()` and the `sylius_paypal.repository.query.pay_pal_payment.refundable_states` parameter | none |

   The following constructor signature has changed:

   ```diff
    // Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQuery
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaymentRepositoryInterface&EntityRepository $paymentRepository,
        private array $updatableStates = [...],
        private array $cancellableStates = [...],
   -    private array $refundableStates = [PaymentInterface::STATE_COMPLETED],
        private array $settleableStates = [...],
    ) {
   ```

   `WebhookRequestVerifier` no longer depends on `PayPalPaymentMethodProviderInterface`.

1. #### `PaymentCaptureProcessor` brings the approved PayPal order up to date through `ApprovedPayPalOrderUpdater`.

   Updating the amount and the shipping address of an approved PayPal order before it is completed moved to
   `Sylius\PayPalPlugin\Updater\ApprovedPayPalOrderUpdaterInterface` (`sylius_paypal.updater.approved_paypal_order`),
   and telling whether the payer has approved it to `Sylius\PayPalPlugin\Checker\ApprovedPayPalOrderCheckerInterface`
   (`sylius_paypal.checker.approved_paypal_order`).

   ```diff
    // Sylius\PayPalPlugin\Processor\PaymentCaptureProcessor
    public function __construct(
   -    private UpdateOrderApiInterface $updateOrderApi,
   -    private UpdateOrderAddressApiInterface $updateOrderAddressApi,
   +    private ApprovedPayPalOrderCheckerInterface $approvedPayPalOrderChecker,
   +    private ApprovedPayPalOrderUpdaterInterface $approvedPayPalOrderUpdater,
        private CompleteOrderApiInterface $completeOrderApi,
        private OrderDetailsApiInterface $orderDetailsApi,
   -    private PaymentUpdaterInterface $paymentUpdater,
   -    private StateResolverInterface $orderPaymentStateResolver,
    ) {
   ```

1. #### A payment method that authorizes offers only the payment sources PayPal can authorize.

   `Sylius\PayPalPlugin\Checker\PaymentAuthorizationCheckerInterface` (`sylius_paypal.checker.payment_authorization`)
   tells whether a payment source is allowed by the payment method's capture strategy. The payment page hides the
   others, and the `cardEnabled` variable tells the card fields template whether to render.

   ```diff
    // Sylius\PayPalPlugin\Checker\PaymentSourceEnabledChecker
   +public function __construct(private PaymentAuthorizationCheckerInterface $paymentAuthorizationChecker)
   ```

   ```diff
    // Sylius\PayPalPlugin\Provider\EligibleRedirectPaymentSourcesProvider
    public function __construct(
        private FindEligibleMethodsApiInterface $findEligibleMethodsApi,
        private PayPalFundingSourcesConfigurationProviderInterface $fundingSourcesConfigurationProvider,
        private LoggerInterface $logger,
   +    private PaymentAuthorizationCheckerInterface $paymentAuthorizationChecker,
    ) {
   ```

   ```diff
    // Sylius\PayPalPlugin\Provider\PayPalPaymentPageContextProvider
    public function __construct(
        // ...
        private EligibleRedirectPaymentSourcesProviderInterface $eligibleRedirectPaymentSourcesProvider,
   +    private PaymentAuthorizationCheckerInterface $paymentAuthorizationChecker,
    ) {
   ```

1. #### The PayPal payment method can authorize payments and capture them later.

   The new "Capture strategy" setting of the PayPal payment method (Immediate capture, or Authorize and capture)
   stores `use_authorize` in its gateway config:

   | `use_authorize` | Sent to PayPal | Payment after the buyer approves |
   |---|---|---|
   | `false` (default) | `"intent": "CAPTURE"`, then `POST /v2/checkout/orders/{id}/capture` | `completed` |
   | `true` | `"intent": "AUTHORIZE"`, then `POST /v2/checkout/orders/{id}/authorize` | `authorized` |

   Like the capture, `AuthorizePayPalOrderPaymentRequestHandler` locks the Payment and then the Payment Request
   (`PESSIMISTIC_WRITE`) before reading its state, so the buyer's return and the `CHECKOUT.ORDER.APPROVED` webhook
   authorizing it at the same time handle it one after the other.

   Sylius picks the `authorize` Payment Request action from the same key, on the order pay page, in express
   checkout and in the shop API. An authorizing payment method offers only the payment sources listed in
   `sylius_paypal.authorize.payment_sources` (PayPal with Pay Later, Venmo, cards, Apple Pay and Google Pay by
   default); redirect methods such as Trustly are hidden. The list accepts only these sources, so it can narrow the
   default but not extend it:

   ```yaml
   sylius_paypal:
       authorize:
           payment_sources: [paypal, venmo, card, apple_pay, google_pay]
   ```

   In 2.x `use_authorize` was a hidden, always `true` flag that made Payum postpone creating the PayPal order; it
   never meant a PayPal authorization. Gateways still on Payum keep it, every other PayPal gateway config is saved with
   the strategy the merchant chose. Set `use_authorize` to `false` on the PayPal gateway configs you upgrade, or they
   will start authorizing instead of capturing.

1. #### An authorized PayPal payment is captured, voided and settled through Payment Requests, webhooks and a command.

   ```mermaid
   stateDiagram-v2
       processing --> authorized: PayPal creates the authorization
       authorized --> completed: capture_authorization request, the capture is COMPLETED
       authorized --> cancelled: order cancelled, then a void_authorization request
       authorized --> cancelled: authorization VOIDED or EXPIRED at PayPal
       processing --> failed: authorization DENIED
       authorized --> failed: authorization DENIED
   ```

   An authorized payment moves on from three places: a Payment Request the shop dispatches, a webhook, or the
   `sylius-paypal:settle-payments` command, which picks up what PayPal does not report.

1. #### An authorized PayPal payment is captured from the admin panel through a Payment Request.

   The "Complete" button of an authorized PayPal payment and `PATCH /api/v2/admin/payments/{id}/paypal-capture`
   dispatch a `capture_authorization` Payment Request. The payment is completed once PayPal reports the capture as
   completed, right away or through the `PAYMENT.CAPTURE.COMPLETED` webhook; a rejected capture fails the request and
   keeps the payment authorized. Until then the core `complete` transition is guarded, so the core "Complete" button is
   hidden and `PATCH /api/v2/admin/payments/{id}/complete` answers `422`.

   | Service | Role |
   |---|---|
   | `sylius_paypal.controller.capture_authorization` (route `sylius_paypal_admin_order_payment_capture_authorization`) | the admin panel button |
   | `sylius_paypal.applicator.payment_capture` | the admin API operation |
   | `sylius_paypal.dispatcher.capture_authorization_payment_request` | dispatches the Payment Request |
   | `sylius_paypal.completer.capture_authorization_payment_request` | completes the request once a pending capture completes |
   | `sylius_paypal.checker.payment_completion` | guards the `complete` transition |

   The `capture_authorization` and `void_authorization` actions are for the admin only, so keep them out of
   `sylius_api.shop_payment_request.allowed_actions`. That list exists since Sylius 2.1.16 and 2.2.9; earlier versions
   let a shop customer request any action, so run one of these versions or later.

1. #### The authorization of a cancelled PayPal payment is voided through a Payment Request.

   Cancelling an authorized PayPal payment, usually by cancelling its order, dispatches a `void_authorization` Payment
   Request (`sylius_paypal.listener.workflow.void_cancelled_authorization`,
   `sylius_paypal.dispatcher.void_authorization_payment_request`). The cancellation does not wait for PayPal: a void
   PayPal rejects fails the request with the reason, and the funds stay held until the authorization expires. When
   PayPal cannot be reached, the request stays processing; with an asynchronous `payment_request` transport Messenger
   retries it and finally keeps it in `payment_request_failed`, from where it is retried with:

   ```bash
   bin/console messenger:failed:retry --transport=payment_request_failed
   ```

   When a payment is cancelled while its order is not, for example after a void made at PayPal, Sylius moves the
   order's `payment_state` back to `awaiting_payment` only since Sylius 2.2.7.

1. #### `sylius-paypal:complete-payments` is renamed to `sylius-paypal:settle-payments` and settles authorized payments.

   The former name stays as an alias. The command settles `authorized` PayPal payments as well as `processing` ones,
   loading them in batches (`--batch-size`, 100 by default). A payment it cannot settle is reported and the next ones
   are still settled; the command then exits with a failure. A capture made in the merchant's PayPal account emits no
   webhook event, and a void made there may not either, so run the command periodically, for example every hour, when
   you authorize payments:

   ```bash
   bin/console sylius-paypal:settle-payments
   ```

   `PAYMENT.AUTHORIZATION.CREATED` and `PAYMENT.AUTHORIZATION.VOIDED` join the webhook event types the plugin
   registers. Subscribe a webhook registered before with `bin/console sylius-paypal:register-webhook-event-types`.

1. #### `OrderDetailsApiInterface::get()` returns a `PayPalOrderDetails` object.

   `Sylius\PayPalPlugin\Api\OrderDetailsApiInterface::get()` returns `Sylius\PayPalPlugin\Model\PayPalOrderDetails`
   instead of the raw PayPal order array. It reads the order status, intent, first purchase unit, capture,
   authorization, and the shipping (`PayPalShipping` with a `PayPalAddress`) and the payer (`Payer`) chosen in the
   wallet; `paymentSource()` keeps PayPal's raw array. Build one from a PayPal order array, e.g. a webhook resource, with
   `Sylius\PayPalPlugin\Mapper\PayPalOrderDetailsMapperInterface::map()` (`sylius_paypal.mapper.paypal_order_details`).

   ```diff
   -public function get(PaymentMethodInterface $paymentMethod, string $orderId): array;
   +public function get(PaymentMethodInterface $paymentMethod, string $orderId): PayPalOrderDetails;
   ```

   ```diff
    // Sylius\PayPalPlugin\Api\OrderDetailsApi
    public function __construct(
        private PayPalClientInterface $client,
   +    private PayPalOrderDetailsMapperInterface $payPalOrderDetailsMapper,
    ) {
   ```

   `Sylius\PayPalPlugin\Verifier\PaymentAmountVerifierInterface::verify()` takes it as well; without it the amount
   still comes from the payment details:

   ```diff
   -public function verify(PaymentInterface $payment, array $paypalOrderDetails = []): void;
   +public function verify(PaymentInterface $payment, ?PayPalOrderDetails $payPalOrderDetails = null): void;
   ```

1. #### The storefront takes the PayPal account of the channel or of the payment being paid.

   The buttons on the product and cart pages, their Pay Later messages and express checkout use the first enabled
   PayPal payment method of the channel by position (`PayPalPaymentMethodsResolverInterface::getInChannel()`); express
   checkout moves a cart paid with another PayPal payment method to it. The buttons and Pay Later messages on the select
   payment page use the PayPal payment method they are shown for: `renderPaymentPageButtonsAction` takes its code as the
   `paymentMethodCode` attribute and passes it to `sylius_paypal_shop_create_paypal_order_from_cart` as the
   `paymentMethod` query parameter, and `sylius_paypal_is_messaging_enabled()`, `sylius_paypal_web_sdk_script_url()` and
   `sylius_paypal_web_sdk_instance_config()` accept an optional payment method. The payment page takes the client id,
   partner attribution id, mode and the enabled payment sources from the payment method of the Payment Request being
   paid.

   ```diff
    // Sylius\PayPalPlugin\Provider\WebSdkConfigurationProviderInterface
   -public function getScriptUrl(): string;
   +public function getScriptUrl(PaymentMethodInterface $paymentMethod): string;
    public function getInstanceConfig(
   -    ChannelInterface $channel,
   +    PaymentMethodInterface $paymentMethod,
        string $pageType,
        array $components = self::DEFAULT_COMPONENTS,
        ?string $locale = null,
    ): array;
   ```

   ```diff
    // Sylius\PayPalPlugin\Provider\PayPalPaymentPageContextProviderInterface
   -public function provide(PaymentInterface $payment, string $locale): array;
   +public function provide(PaymentRequestInterface $paymentRequest, string $locale): array;
   ```

   The payment page context also carries `sandbox`, `messagingEnabled` and `messagingInstanceConfig`; the
   `sylius_is_paypal_sandbox()` Twig function has been removed, use `context.sandbox` on the payment page.

   The following constructor signatures have changed:

   | Class | Change |
   |---|---|
   | `Sylius\PayPalPlugin\Provider\WebSdkConfigurationProvider` | takes only `HostProviderInterface` and the test buyer country |
   | `Sylius\PayPalPlugin\ApiPlatform\PayPalPayment` | lost `PayPalConfigurationProviderInterface`; the partner attribution id comes from the payment method |
   | `Sylius\PayPalPlugin\Provider\PayPalPaymentPageContextProvider` | lost `PayPalFundingSourcesConfigurationProviderInterface` |
   | `Sylius\PayPalPlugin\Provider\EligibleRedirectPaymentSourcesProvider` | lost `PayPalFundingSourcesConfigurationProviderInterface` |
   | `Sylius\PayPalPlugin\Provider\PayPalConfigurationProvider` | takes `PayPalPaymentMethodsResolverInterface` instead of `PaymentMethodRepositoryInterface` |
   | `Sylius\PayPalPlugin\Controller\PayPalButtonsController`, `Sylius\PayPalPlugin\Twig\PayPalExtension` | gained `PayPalPaymentMethodsResolverInterface` as the last argument |
   | `Sylius\PayPalPlugin\Controller\CreatePayPalOrderFromCartAction` | lost `PayPalFundingSourcesConfigurationProviderInterface`; Venmo is checked on the PayPal payment method of the order |
