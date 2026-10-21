<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\AmountUtils;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PayPalPaymentPageContextProvider implements PayPalPaymentPageContextProviderInterface
{
    public const PAGE_TYPE = 'checkout';

    public const CARD_FIELDS_COMPONENT = 'card-fields';

    public const GOOGLE_PAY_COMPONENT = 'googlepay-payments';

    public const APPLE_PAY_COMPONENT = 'applepay-payments';

    public const VENMO_COMPONENT = 'venmo-payments';

    public const MESSAGES_COMPONENT = 'paypal-messages';

    public function __construct(
        private WebSdkConfigurationProviderInterface $webSdkConfigurationProvider,
        private UrlGeneratorInterface $router,
        private LocaleProcessorInterface $localeProcessor,
        private EligibleRedirectPaymentSourcesProviderInterface $eligibleRedirectPaymentSourcesProvider,
    ) {
    }

    public function provide(PaymentRequestInterface $paymentRequest, string $locale): array
    {
        /** @var PaymentInterface $payment */
        $payment = $paymentRequest->getPayment();
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $paymentRequest->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);
        /** @var OrderInterface $order */
        $order = $payment->getOrder();
        /** @var ChannelInterface $channel */
        $channel = $order->getChannel();

        $messagingEnabled = $config->isPayLaterEnabled() && $config->isMessagingEnabled();

        try {
            $processedLocale = $this->localeProcessor->process($locale);
        } catch (\UnexpectedValueException) {
            $processedLocale = null;
        }

        return [
            'amount' => AmountUtils::toPayPalValue((int) $payment->getAmount(), (string) $order->getCurrencyCode()),
            'applePayEnabled' => $config->isApplePayEnabled(),
            'billingAddress' => $order->getBillingAddress(),
            'countryCode' => $channel->getShopBillingData()?->getCountryCode(),
            'currency' => $order->getCurrencyCode(),
            'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_shop_payment_error'),
            'googlePayEnabled' => $config->isGooglePayEnabled(),
            'languageCode' => $this->languageCode($locale),
            'locale' => $processedLocale,
            'order' => $order,
            'payment' => $payment,
            'messagingEnabled' => $messagingEnabled,
            'messagingInstanceConfig' => $messagingEnabled
                ? $this->webSdkConfigurationProvider->getInstanceConfig($paymentMethod, self::PAGE_TYPE, [self::MESSAGES_COMPONENT], $processedLocale)
                : [],
            'paylaterEnabled' => $config->isPayLaterEnabled(),
            'redirectPaymentSources' => $this->redirectPaymentSources($payment),
            'sandbox' => $config->isSandbox(),
            'venmoEnabled' => $config->isVenmoEnabled(),
            'webSdkInstanceConfig' => $this->webSdkConfigurationProvider->getInstanceConfig(
                $paymentMethod,
                self::PAGE_TYPE,
                $this->components($config),
                $processedLocale,
            ),
            'webSdkScriptUrl' => $this->webSdkConfigurationProvider->getScriptUrl($paymentMethod),
        ];
    }

    /** @return array<string, string> */
    private function redirectPaymentSources(PaymentInterface $payment): array
    {
        $paymentSources = [];

        foreach ($this->eligibleRedirectPaymentSourcesProvider->provide($payment) as $case) {
            $paymentSources[$case->value] = $case->iconUrl();
        }

        return $paymentSources;
    }

    private function languageCode(string $locale): string
    {
        return strtolower(preg_split('/[_-]/', trim($locale))[0] ?? '');
    }

    /** @return array<int, string> */
    private function components(PayPalGatewayConfig $config): array
    {
        $components = [...WebSdkConfigurationProviderInterface::DEFAULT_COMPONENTS, self::CARD_FIELDS_COMPONENT];

        if ($config->isGooglePayEnabled()) {
            $components[] = self::GOOGLE_PAY_COMPONENT;
        }

        if ($config->isApplePayEnabled()) {
            $components[] = self::APPLE_PAY_COMPONENT;
        }

        if ($config->isVenmoEnabled()) {
            $components[] = self::VENMO_COMPONENT;
        }

        return $components;
    }
}
