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
   | `sylius_paypal.sandbox` parameter | `Sylius\PayPalPlugin\Provider\ActiveModeProviderInterface` (`sylius_paypal.provider.active_mode`) |
   | `sylius_paypal.api_base_url` parameter | `Sylius\PayPalPlugin\Provider\HostProviderInterface` (`sylius_paypal.provider.host`) |
   | `sylius_paypal.web_url` parameter | `Sylius\PayPalPlugin\Provider\HostProviderInterface` (`sylius_paypal.provider.host`) |
   | `sylius_paypal.reports_sftp_host` parameter | `Sylius\PayPalPlugin\Provider\HostProviderInterface` (`sylius_paypal.provider.host`) |
   | `sylius_paypal.partner_js_url` parameter | `Sylius\PayPalPlugin\Provider\HostProviderInterface::getWebUrlForMode()` (used by the `sylius_paypal_partner_js_url()` Twig function) |
   | `sylius_paypal.client.sftp` service | `Sylius\PayPalPlugin\Factory\SftpClientFactoryInterface` (`sylius_paypal.factory.sftp_client`) |

   The following constructor signatures have changed:

   ```diff
    // Sylius\PayPalPlugin\Provider\WebhookIdProvider
    public function __construct(
        private GenericApiInterface $genericApi,
   -    private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private UrlGeneratorInterface $urlGenerator,
   -    private string $baseUrl,
   +    private HostProviderInterface $hostProvider,
        // ...
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
   +    private readonly ActiveModeProviderInterface $activeModeProvider,
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

   Network errors of any PSR-18 client now end in `PayPalApiTimeoutException`; before, only Guzzle's
   `ConnectException` did. `sylius_paypal.http_client` is still an alias of `sylius.http_client`; point it to a
   scoped HTTP client to set timeouts for the PayPal API.

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
   method's mode (`HostProviderInterface::getApiBaseUrlForMode()`), the same one its token was issued by. The token
   request moved to `AuthorizeClientApi`.

   ```diff
    // Sylius\PayPalPlugin\Client\PayPalClient
    public function __construct(
   -    private ClientInterface $client,
   +    private ClientInterface $client, // sylius_paypal.http_client.retrying
        private LoggerInterface $logger,
        private UuidProviderInterface $uuidProvider,
        private PayPalConfigurationProviderInterface $payPalConfigurationProvider,
        private ChannelContextInterface $channelContext,
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
   | `Sylius\PayPalPlugin\Api\UpdateWebhookApiInterface` | `updateEventTypes()` |
   | `Sylius\PayPalPlugin\Api\WebhookApiInterface` | `register()` |
   | `Sylius\PayPalPlugin\PackageTracking\Api\AddTrackingApiInterface` | `add()` |

   `WebhookSignatureVerifierInterface::verify()` takes the payment method as its third argument instead of the
   token:

   ```diff
   -public function verify(Request $request, string $webhookId, string $token): bool;
   +public function verify(Request $request, string $webhookId, PaymentMethodInterface $paymentMethod): bool;
   ```

   `WebhookApi` and `UpdateWebhookApi` send through `PayPalClientInterface`, their only constructor argument now.
   `WebhookSignatureVerifier` and `GenericApi` take the token from `CacheAuthorizeClientApiInterface`, and the
   signature verifier recovers from a `401` the same way the client does. The signature verifier asks the API of the
   payment method's mode.

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

   ```diff
    // Sylius\PayPalPlugin\Api\GenericApi
    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
   +    private CacheAuthorizeClientApiInterface $authorizeClientApi,
    ) {
   ```

1. #### Services that used to fetch the access token no longer depend on the token cache.

   The `CacheAuthorizeClientApiInterface $authorizeClientApi` argument has been removed from the constructors of:

   - `Sylius\PayPalPlugin\Controller\ProcessPayPalOrderAction`
   - `Sylius\PayPalPlugin\Controller\UpdatePayPalOrderAction`
   - `Sylius\PayPalPlugin\Controller\Webhook\RefundOrderAction`
   - `Sylius\PayPalPlugin\Creator\PayPalOrderCreator`
   - `Sylius\PayPalPlugin\PackageTracking\Processor\ShipmentTrackingProcessor`
   - `Sylius\PayPalPlugin\Processor\PaymentCaptureProcessor`
   - `Sylius\PayPalPlugin\Processor\PayPalLateCaptureRefundProcessor`
   - `Sylius\PayPalPlugin\Processor\PayPalPaymentRefundProcessor`
   - `Sylius\PayPalPlugin\Processor\PayPalPaymentSettlementProcessor`
   - `Sylius\PayPalPlugin\Provider\EligibleRedirectPaymentSourcesProvider`
   - `Sylius\PayPalPlugin\Provider\PayPalRefundDataProvider`
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
   +    /** @throws PayPalAuthorizationException */
   +    public function authorize(string $clientId, string $clientSecret, bool $sandbox): AccessToken;
    }
   ```

   The token is requested from the API of the given mode. `Sylius\PayPalPlugin\Model\AccessToken` carries the token
   (`value()`) and the lifetime PayPal gives it (`expiresIn()`). A response without the token or its lifetime throws
   `PayPalAuthorizationException` instead of returning an empty string.

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

1. #### A Payment Request captures its PayPal order under a request id derived from the request.

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

   `CapturePayPalOrderPaymentRequestHandler` passes `{payment request id}-capture` as the `PayPal-Request-Id`, so
   reprocessing the same Payment Request makes PayPal return the stored result of the first capture instead of
   capturing again. Without a request id, a random one is used, as before.
