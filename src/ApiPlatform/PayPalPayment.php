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

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Provider\PaymentSdkConfigurationProviderInterface;

final class PayPalPayment
{
    private PaymentSdkConfigurationProviderInterface $paymentSdkConfigurationProvider;

    public function __construct(PaymentSdkConfigurationProviderInterface $paymentSdkConfigurationProvider)
    {
        $this->paymentSdkConfigurationProvider = $paymentSdkConfigurationProvider;
    }

    public function supports(PaymentMethodInterface $paymentMethod): bool
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return $gatewayConfig->getFactoryName() === SyliusPayPalExtension::PAYPAL_FACTORY_NAME;
    }

    public function provideConfiguration(PaymentInterface $payment): array
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        return $this->paymentSdkConfigurationProvider->provide($payment, $paymentMethod, (string) $order->getLocaleCode());
    }
}
