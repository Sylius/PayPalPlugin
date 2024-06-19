### UPGRADE FROM 1.5.1 to 1.6

1. Support for Sylius 1.13 has been added, it is now the recommended Sylius version to use.

1. Support for PHP 8.0 has been dropped.

1. The following constructor signatures have been changed:

    `Sylius\PayPalPlugin\Client\PayPalClient`:
     ```diff
     use Psr\Http\Client\ClientInterface;
     use GuzzleHttp\ClientInterface as GuzzleClientInterface;
     use Psr\Http\Message\RequestFactoryInterface;
     use Psr\Http\Message\StreamFactoryInterface;

        public function __construct(
    -      private readonly GuzzleClientInterface $client,
    +      private readonly GuzzleClientInterface|ClientInterface $client,
            private readonly LoggerInterface $logger,
            private readonly UuidProviderInterface $uuidProvider,
            private readonly PayPalConfigurationProviderInterface $payPalConfigurationProvider,
            private readonly ChannelContextInterface $channelContext,
            private readonly string $baseUrl,
            private int $requestTrialsLimit,
            private readonly bool $loggingLevelIncreased = false,
    +      private readonly ?RequestFactoryInterface $requestFactory = null,
    +      private readonly ?StreamFactoryInterface $streamFactory = null,
        )
     ```

   `Sylius\PayPalPlugin\Api\GeneralApi`:
     ```diff
     use Psr\Http\Client\ClientInterface;
     use GuzzleHttp\ClientInterface as GuzzleClientInterface;
     use Psr\Http\Message\RequestFactoryInterface;

        public function __construct(
   -      private readonly GuzzleClientInterface $client,
   +      private readonly GuzzleClientInterface|ClientInterface $client,
   +      private readonly ?RequestFactoryInterface $requestFactory = null,
    )
     ```

   `Sylius\PayPalPlugin\Api\WebhookApi`:
     ```diff
     use Psr\Http\Client\ClientInterface;
     use GuzzleHttp\ClientInterface as GuzzleClientInterface;
     use Psr\Http\Message\RequestFactoryInterface;
     use Psr\Http\Message\StreamFactoryInterface;

        public function __construct(
   -       private readonly GuzzleClientInterface $client,
   +       private readonly GuzzleClientInterface|ClientInterface $client,
             private readonly string $baseUrl,
   +       private readonly ?RequestFactoryInterface $requestFactory = null,
   +       private readonly ?StreamFactoryInterface $streamFactory = null,
    )
     ```

   `Sylius\PayPalPlugin\Onboarding\Processor\BasicOnboardingProcessor`:
     ```diff
      use Psr\Http\Client\ClientInterface;
      use GuzzleHttp\ClientInterface as GuzzleClientInterface;
      use Psr\Http\Message\RequestFactoryInterface;

        public function __construct(
   -      private readonly GuzzleClientInterface $client,
   +      private readonly GuzzleClientInterface|ClientInterface $client,
            private readonly SellerWebhookRegistrarInterface $sellerWebhookRegistrar,
            private readonly string $url,
   +      private readonly ?RequestFactoryInterface $requestFactory = null,
    )
     ```

   `Sylius\PayPalPlugin\Model\PayPalPurchaseUnit`:
     ```diff
      use Sylius\Component\Core\Model\AddressInterface;
      use Webmozart\Assert\Assert;

        public function __construct(
            private readonly string $referenceId,
            private readonly string $invoiceNumber,
            private readonly string $currencyCode,
            private readonly int $totalAmount,
            private readonly int $shippingValue,
            private readonly float $itemTotalValue,
            private readonly float $taxTotalValue,
            private readonly int $discountValue,
            private readonly string $merchantId,
            private readonly array $items,
            private readonly bool $shippingRequired,
            private readonly ?AddressInterface $shippingAddress = null,
            private readonly string $softDescriptor = 'Sylius PayPal Payment',
   +      private readonly int $shippingDiscountValue = 0,
    )
     ```

1. Added doctrine migration for PostgreSQL. For more information, please refer to the [Sylius 1.13 UPGRADE.md](https://github.com/Sylius/Sylius/blob/1.13/UPGRADE-1.13.md)

### UPGRADE FROM 1.3.0 to 1.3.1

1. `sylius_paypal_plugin_pay_with_paypal_form` route now operates on both payment ID and order token. URl then changed from
   `/pay-with-paypal/{id}` to `/pay-with-paypal/{orderToken}/{paymentId}`. If you use this route anywhere in your application, you
   need to change the URL attributes

### UPGRADE FROM 1.2.3 to 1.2.4

1. `sylius_paypal_plugin_pay_with_paypal_form` route now operates on both payment ID and order token. URl then changed from
    `/pay-with-paypal/{id}` to `/pay-with-paypal/{orderToken}/{paymentId}`. If you use this route anywhere in your application, you
    need to change the URL attributes

### UPGRADE FROM 1.0.X TO 1.1.0

1. Upgrade your application to [Sylius 1.8](https://github.com/Sylius/Sylius/blob/master/UPGRADE-1.8.md).

1. Remove previously copied migration files (You may check migrations to remove [here](https://github.com/Sylius/PayPalPlugin/pull/160/files)).
