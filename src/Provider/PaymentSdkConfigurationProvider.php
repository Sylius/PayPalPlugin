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
use Sylius\PayPalPlugin\Checker\PaymentAuthorizationCheckerInterface;
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

    public function __construct(
        private WebSdkConfigurationProviderInterface $webSdkConfigurationProvider,
        private LocaleProcessorInterface $localeProcessor,
        private EligibleRedirectPaymentSourcesProviderInterface $eligibleRedirectPaymentSourcesProvider,
        private PaymentAuthorizationCheckerInterface $paymentAuthorizationChecker,
    ) {
    }

    public function provide(PaymentInterface $payment, PaymentMethodInterface $paymentMethod, string $locale): array
    {
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
            'applePayEnabled' => $this->isApplePayEnabled($config, $paymentMethod),
            'countryCode' => $channel->getShopBillingData()?->getCountryCode(),
            'currency' => $order->getCurrencyCode(),
            'cardEnabled' => $this->paymentAuthorizationChecker->isAllowed(PayPalPaymentSourceProviderInterface::CARD, $paymentMethod),
            'googlePayEnabled' => $this->isGooglePayEnabled($config, $paymentMethod),
            'languageCode' => $this->languageCode($locale),
            'locale' => $processedLocale,
            'messagingEnabled' => $messagingEnabled,
            'messagingInstanceConfig' => $messagingEnabled
                ? $this->webSdkConfigurationProvider->getInstanceConfig($paymentMethod, self::PAGE_TYPE, [self::MESSAGES_COMPONENT], $processedLocale)
                : [],
            'paylaterEnabled' => $config->isPayLaterEnabled(),
            'redirectPaymentSources' => $this->redirectPaymentSources($payment),
            'sandbox' => $config->isSandbox(),
            'venmoEnabled' => $this->isVenmoEnabled($config, $paymentMethod),
            'webSdkInstanceConfig' => $this->webSdkConfigurationProvider->getInstanceConfig(
                $paymentMethod,
                self::PAGE_TYPE,
                $this->components($config, $paymentMethod),
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
    private function components(PayPalGatewayConfig $config, PaymentMethodInterface $paymentMethod): array
    {
        $components = WebSdkConfigurationProviderInterface::DEFAULT_COMPONENTS;

        if ($this->paymentAuthorizationChecker->isAllowed(PayPalPaymentSourceProviderInterface::CARD, $paymentMethod)) {
            $components[] = self::CARD_FIELDS_COMPONENT;
        }

        if ($this->isGooglePayEnabled($config, $paymentMethod)) {
            $components[] = self::GOOGLE_PAY_COMPONENT;
        }

        if ($this->isApplePayEnabled($config, $paymentMethod)) {
            $components[] = self::APPLE_PAY_COMPONENT;
        }

        if ($this->isVenmoEnabled($config, $paymentMethod)) {
            $components[] = self::VENMO_COMPONENT;
        }

        return $components;
    }

    private function isGooglePayEnabled(PayPalGatewayConfig $config, PaymentMethodInterface $paymentMethod): bool
    {
        return
            $config->isGooglePayEnabled() &&
            $this->paymentAuthorizationChecker->isAllowed(PayPalPaymentSourceProviderInterface::GOOGLE_PAY, $paymentMethod)
        ;
    }

    private function isApplePayEnabled(PayPalGatewayConfig $config, PaymentMethodInterface $paymentMethod): bool
    {
        return
            $config->isApplePayEnabled() &&
            $this->paymentAuthorizationChecker->isAllowed(PayPalPaymentSourceProviderInterface::APPLE_PAY, $paymentMethod)
        ;
    }

    private function isVenmoEnabled(PayPalGatewayConfig $config, PaymentMethodInterface $paymentMethod): bool
    {
        return
            $config->isVenmoEnabled() &&
            $this->paymentAuthorizationChecker->isAllowed(PayPalPaymentSourceProviderInterface::VENMO, $paymentMethod)
        ;
    }
}
