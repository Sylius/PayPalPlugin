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
    // Sylius\PayPalPlugin\Client\PayPalClient
    public function __construct(
        // ...
   -    private readonly string $baseUrl,
   +    private readonly HostProviderInterface $hostProvider,
        // ...
    ) {
   ```

   ```diff
    // Sylius\PayPalPlugin\Api\WebhookApi
    // Sylius\PayPalPlugin\Api\WebhookSignatureVerifier
    public function __construct(
        private ClientInterface $client,
   -    private string $baseUrl,
   +    private HostProviderInterface $hostProvider,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
   ```

   ```diff
    // Sylius\PayPalPlugin\Provider\WebhookIdProvider
    public function __construct(
        private GenericApiInterface $genericApi,
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
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

   ```diff
    // Sylius\PayPalPlugin\Controller\PayWithPayPalFormAction
    public function __construct(
        // ...
   -    private ?string $webUrl = null,
   +    private ?HostProviderInterface $hostProvider = null,
    ) {
   ```

   The following methods take the mode explicitly instead of reading it from the active payment method:

   ```diff
    // Sylius\PayPalPlugin\Client\PayPalClientInterface
   -public function authorize(string $clientId, string $clientSecret): array;
   +public function authorize(string $clientId, string $clientSecret, bool $sandbox): array;
   ```

   ```diff
    // Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface
   -public function authorize(string $clientId, string $clientSecret): string;
   +public function authorize(string $clientId, string $clientSecret, bool $sandbox): string;
   ```

   ```diff
    // Sylius\PayPalPlugin\Api\WebhookApiInterface
   -public function register(string $token, string $webhookUrl): array;
   +public function register(string $token, string $webhookUrl, bool $sandbox): array;
   ```
