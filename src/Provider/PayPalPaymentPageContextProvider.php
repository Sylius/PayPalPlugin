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
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PayPalPaymentPageContextProvider implements PayPalPaymentPageContextProviderInterface
{
    public const PAGE_TYPE = 'checkout';

    public const CARD_FIELDS_COMPONENT = 'card-fields';

    public const GOOGLE_PAY_COMPONENT = 'googlepay-payments';

    public const VENMO_COMPONENT = 'venmo-payments';

    public function __construct(
        private PayPalWebSdkConfigurationProviderInterface $webSdkConfigurationProvider,
        private UrlGeneratorInterface $router,
        private LocaleProcessorInterface $localeProcessor,
        private PayPalFundingSourcesConfigurationProviderInterface $fundingSourcesConfigurationProvider,
        private EligibleRedirectPaymentSourcesProviderInterface $eligibleRedirectPaymentSourcesProvider,
    ) {
    }

    public function provide(PaymentInterface $payment, string $locale): array
    {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();
        /** @var ChannelInterface $channel */
        $channel = $order->getChannel();

        $processedLocale = $this->localeProcessor->process($locale);

        return [
            'amount' => number_format($payment->getAmount() / 100, 2, '.', ''),
            'billingAddress' => $order->getBillingAddress(),
            'cancelPayPalPaymentUrl' => $this->router->generate('sylius_paypal_shop_cancel_checkout_payment'),
            'completePayPalOrderUrl' => $this->router->generate(
                'sylius_paypal_shop_complete_paypal_order',
                ['token' => $order->getTokenValue()],
            ),
            'createPayPalOrderUrl' => $this->router->generate(
                'sylius_paypal_shop_create_paypal_order',
                ['token' => $order->getTokenValue()],
            ),
            'currency' => $order->getCurrencyCode(),
            'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_shop_payment_error'),
            'googlePayEnabled' => $this->fundingSourcesConfigurationProvider->isGooglePayEnabled($channel),
            'languageCode' => $this->languageCode($locale),
            'locale' => $processedLocale,
            'order' => $order,
            'payment' => $payment,
            'paylaterEnabled' => $this->fundingSourcesConfigurationProvider->isPayLaterEnabled($channel),
            'redirectPaymentSources' => $this->redirectPaymentSources($payment),
            'venmoEnabled' => $this->fundingSourcesConfigurationProvider->isVenmoEnabled($channel),
            'webSdkInstanceConfig' => $this->webSdkConfigurationProvider->getInstanceConfig(
                $channel,
                self::PAGE_TYPE,
                $this->components($channel),
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
    private function components(ChannelInterface $channel): array
    {
        $components = [...PayPalWebSdkConfigurationProviderInterface::DEFAULT_COMPONENTS, self::CARD_FIELDS_COMPONENT];

        if ($this->fundingSourcesConfigurationProvider->isGooglePayEnabled($channel)) {
            $components[] = self::GOOGLE_PAY_COMPONENT;
        }

        if ($this->fundingSourcesConfigurationProvider->isVenmoEnabled($channel)) {
            $components[] = self::VENMO_COMPONENT;
        }

        return $components;
    }
}
