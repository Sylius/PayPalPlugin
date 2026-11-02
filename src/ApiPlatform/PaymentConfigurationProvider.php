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

namespace Sylius\PayPalPlugin\ApiPlatform;

use Sylius\Bundle\ApiBundle\Payment\PaymentConfigurationProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Provider\PaymentSdkConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;

final readonly class PaymentConfigurationProvider implements PaymentConfigurationProviderInterface
{
    private const PAYMENT_SOURCE_FLAGS = [
        PayPalPaymentSourceProviderInterface::CARD => 'cardEnabled',
        PayPalPaymentSourceProviderInterface::VENMO => 'venmoEnabled',
        PayPalPaymentSourceProviderInterface::GOOGLE_PAY => 'googlePayEnabled',
        PayPalPaymentSourceProviderInterface::APPLE_PAY => 'applePayEnabled',
    ];

    public function __construct(private PaymentSdkConfigurationProviderInterface $paymentSdkConfigurationProvider)
    {
    }

    public function supports(PaymentMethodInterface $paymentMethod): bool
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return $gatewayConfig->getFactoryName() === SyliusPayPalExtension::PAYPAL_FACTORY_NAME;
    }

    public function provideConfiguration(PaymentInterface $payment): array
    {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $configuration = $this->paymentSdkConfigurationProvider->provide($payment, (string) $order->getLocaleCode());

        return [
            'webSdkScriptUrl' => $configuration['webSdkScriptUrl'],
            'webSdkInstanceConfig' => $configuration['webSdkInstanceConfig'],
            'amount' => $configuration['amount'],
            'currency' => $configuration['currency'],
            'countryCode' => $configuration['countryCode'],
            'languageCode' => $configuration['languageCode'],
            'sandbox' => $configuration['sandbox'],
            'paymentSources' => $this->paymentSources($configuration),
            'payLaterEnabled' => $configuration['paylaterEnabled'],
            'messagingInstanceConfig' => $configuration['messagingEnabled'] ? $configuration['messagingInstanceConfig'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return list<string>
     */
    private function paymentSources(array $configuration): array
    {
        $paymentSources = [PayPalPaymentSourceProviderInterface::PAYPAL];
        foreach (self::PAYMENT_SOURCE_FLAGS as $paymentSource => $flag) {
            if (true === $configuration[$flag]) {
                $paymentSources[] = $paymentSource;
            }
        }

        /** @var array<string, string> $redirectPaymentSources */
        $redirectPaymentSources = $configuration['redirectPaymentSources'];

        return [...$paymentSources, ...array_keys($redirectPaymentSources)];
    }
}
