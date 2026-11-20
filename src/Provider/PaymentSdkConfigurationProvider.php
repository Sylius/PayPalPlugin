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
use Sylius\PayPalPlugin\AmountUtils;
use Sylius\PayPalPlugin\Checker\PaymentSourceEnabledCheckerInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;

final readonly class PaymentSdkConfigurationProvider implements PaymentSdkConfigurationProviderInterface
{
    public const PAGE_TYPE = 'checkout';

    public const CARD_FIELDS_COMPONENT = 'card-fields';

    public const GOOGLE_PAY_COMPONENT = 'googlepay-payments';

    public const APPLE_PAY_COMPONENT = 'applepay-payments';

    public const VENMO_COMPONENT = 'venmo-payments';

    public const MESSAGES_COMPONENT = 'paypal-messages';

    private const COMPONENTS = [
        PayPalPaymentSourceProviderInterface::CARD => self::CARD_FIELDS_COMPONENT,
        PayPalPaymentSourceProviderInterface::GOOGLE_PAY => self::GOOGLE_PAY_COMPONENT,
        PayPalPaymentSourceProviderInterface::APPLE_PAY => self::APPLE_PAY_COMPONENT,
        PayPalPaymentSourceProviderInterface::VENMO => self::VENMO_COMPONENT,
    ];

    public function __construct(
        private WebSdkConfigurationProviderInterface $webSdkConfigurationProvider,
        private LocaleProcessorInterface $localeProcessor,
        private EligibleRedirectPaymentSourcesProviderInterface $eligibleRedirectPaymentSourcesProvider,
        private PaymentSourceEnabledCheckerInterface $paymentSourceEnabledChecker,
    ) {
    }

    public function provide(PaymentInterface $payment, string $locale): array
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);
        /** @var OrderInterface $order */
        $order = $payment->getOrder();
        /** @var ChannelInterface $channel */
        $channel = $order->getChannel();

        $messagingEnabled = $config->isPayLaterEnabled() && $config->isMessagingEnabled();
        $enabledPaymentSources = $this->enabledPaymentSources($paymentMethod);

        try {
            $processedLocale = $this->localeProcessor->process($locale);
        } catch (\UnexpectedValueException) {
            $processedLocale = null;
        }

        return [
            'amount' => AmountUtils::toPayPalValue((int) $payment->getAmount(), (string) $order->getCurrencyCode()),
            'applePayEnabled' => $enabledPaymentSources[PayPalPaymentSourceProviderInterface::APPLE_PAY],
            'countryCode' => $channel->getShopBillingData()?->getCountryCode(),
            'currency' => $order->getCurrencyCode(),
            'cardEnabled' => $enabledPaymentSources[PayPalPaymentSourceProviderInterface::CARD],
            'googlePayEnabled' => $enabledPaymentSources[PayPalPaymentSourceProviderInterface::GOOGLE_PAY],
            'languageCode' => $this->languageCode($locale),
            'locale' => $processedLocale,
            'messagingEnabled' => $messagingEnabled,
            'messagingInstanceConfig' => $messagingEnabled
                ? $this->webSdkConfigurationProvider->getInstanceConfig($paymentMethod, self::PAGE_TYPE, [self::MESSAGES_COMPONENT], $processedLocale)
                : [],
            'paylaterEnabled' => $config->isPayLaterEnabled(),
            'redirectPaymentSources' => $this->redirectPaymentSources($payment),
            'sandbox' => $config->isSandbox(),
            'venmoEnabled' => $enabledPaymentSources[PayPalPaymentSourceProviderInterface::VENMO],
            'webSdkInstanceConfig' => $this->webSdkConfigurationProvider->getInstanceConfig(
                $paymentMethod,
                self::PAGE_TYPE,
                $this->components($enabledPaymentSources),
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

    /** @return array<string, bool> */
    private function enabledPaymentSources(PaymentMethodInterface $paymentMethod): array
    {
        $enabledPaymentSources = [];
        foreach (array_keys(self::COMPONENTS) as $paymentSource) {
            $enabledPaymentSources[$paymentSource] = $this->paymentSourceEnabledChecker->isEnabled($paymentSource, $paymentMethod);
        }

        return $enabledPaymentSources;
    }

    /**
     * @param array<string, bool> $enabledPaymentSources
     *
     * @return array<int, string>
     */
    private function components(array $enabledPaymentSources): array
    {
        $components = WebSdkConfigurationProviderInterface::DEFAULT_COMPONENTS;
        foreach (self::COMPONENTS as $paymentSource => $component) {
            if ($enabledPaymentSources[$paymentSource]) {
                $components[] = $component;
            }
        }

        return $components;
    }
}
