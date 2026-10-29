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
use Sylius\PayPalPlugin\AmountUtils;
use Sylius\PayPalPlugin\Checker\PaymentAuthorizationCheckerInterface;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PayPalPaymentPageContextProvider implements PayPalPaymentPageContextProviderInterface
{
    public const PAGE_TYPE = 'checkout';

    public const CARD_FIELDS_COMPONENT = 'card-fields';

    public const GOOGLE_PAY_COMPONENT = 'googlepay-payments';

    public const APPLE_PAY_COMPONENT = 'applepay-payments';

    public const VENMO_COMPONENT = 'venmo-payments';

    public function __construct(
        private WebSdkConfigurationProviderInterface $webSdkConfigurationProvider,
        private UrlGeneratorInterface $router,
        private LocaleProcessorInterface $localeProcessor,
        private PayPalFundingSourcesConfigurationProviderInterface $fundingSourcesConfigurationProvider,
        private EligibleRedirectPaymentSourcesProviderInterface $eligibleRedirectPaymentSourcesProvider,
        private PaymentAuthorizationCheckerInterface $paymentAuthorizationChecker,
    ) {
    }

    public function provide(PaymentInterface $payment, string $locale): array
    {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();
        /** @var ChannelInterface $channel */
        $channel = $order->getChannel();

        try {
            $processedLocale = $this->localeProcessor->process($locale);
        } catch (\UnexpectedValueException) {
            $processedLocale = null;
        }

        return [
            'amount' => AmountUtils::toPayPalValue((int) $payment->getAmount(), (string) $order->getCurrencyCode()),
            'applePayEnabled' => $this->isApplePayEnabled($payment, $channel),
            'billingAddress' => $order->getBillingAddress(),
            'countryCode' => $channel->getShopBillingData()?->getCountryCode(),
            'currency' => $order->getCurrencyCode(),
            'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_shop_payment_error'),
            'cardEnabled' => $this->isAllowed($payment, PayPalPaymentSourceProviderInterface::CARD),
            'googlePayEnabled' => $this->isGooglePayEnabled($payment, $channel),
            'languageCode' => $this->languageCode($locale),
            'locale' => $processedLocale,
            'order' => $order,
            'payment' => $payment,
            'paylaterEnabled' => $this->fundingSourcesConfigurationProvider->isPayLaterEnabled($channel),
            'redirectPaymentSources' => $this->redirectPaymentSources($payment),
            'venmoEnabled' => $this->isVenmoEnabled($payment, $channel),
            'webSdkInstanceConfig' => $this->webSdkConfigurationProvider->getInstanceConfig(
                $channel,
                self::PAGE_TYPE,
                $this->components($payment, $channel),
                $processedLocale,
            ),
            'webSdkScriptUrl' => $this->webSdkConfigurationProvider->getScriptUrl(),
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
    private function components(PaymentInterface $payment, ChannelInterface $channel): array
    {
        $components = WebSdkConfigurationProviderInterface::DEFAULT_COMPONENTS;

        if ($this->isAllowed($payment, PayPalPaymentSourceProviderInterface::CARD)) {
            $components[] = self::CARD_FIELDS_COMPONENT;
        }

        if ($this->isGooglePayEnabled($payment, $channel)) {
            $components[] = self::GOOGLE_PAY_COMPONENT;
        }

        if ($this->isApplePayEnabled($payment, $channel)) {
            $components[] = self::APPLE_PAY_COMPONENT;
        }

        if ($this->isVenmoEnabled($payment, $channel)) {
            $components[] = self::VENMO_COMPONENT;
        }

        return $components;
    }

    private function isGooglePayEnabled(PaymentInterface $payment, ChannelInterface $channel): bool
    {
        return
            $this->fundingSourcesConfigurationProvider->isGooglePayEnabled($channel) &&
            $this->isAllowed($payment, PayPalPaymentSourceProviderInterface::GOOGLE_PAY)
        ;
    }

    private function isApplePayEnabled(PaymentInterface $payment, ChannelInterface $channel): bool
    {
        return
            $this->fundingSourcesConfigurationProvider->isApplePayEnabled($channel) &&
            $this->isAllowed($payment, PayPalPaymentSourceProviderInterface::APPLE_PAY)
        ;
    }

    private function isVenmoEnabled(PaymentInterface $payment, ChannelInterface $channel): bool
    {
        return
            $this->fundingSourcesConfigurationProvider->isVenmoEnabled($channel) &&
            $this->isAllowed($payment, PayPalPaymentSourceProviderInterface::VENMO)
        ;
    }

    private function isAllowed(PaymentInterface $payment, string $paymentSource): bool
    {
        $paymentMethod = $payment->getMethod();

        return null === $paymentMethod || $this->paymentAuthorizationChecker->isAllowed($paymentSource, $paymentMethod);
    }
}
