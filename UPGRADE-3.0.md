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

1. #### The following signatures have gained new optional arguments.

   ```diff
    // Sylius\PayPalPlugin\Twig\PayPalExtension
    public function __construct(
        // ...
   +    private readonly string $partnerJsUrl = '',
    ) {
   ```

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
