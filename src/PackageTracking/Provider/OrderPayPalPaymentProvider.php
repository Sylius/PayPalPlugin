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

namespace Sylius\PayPalPlugin\PackageTracking\Provider;

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;

final class OrderPayPalPaymentProvider implements OrderPayPalPaymentProviderInterface
{
    public function provide(OrderInterface $order): ?PaymentInterface
    {
        foreach (array_reverse($order->getPayments()->toArray()) as $payment) {
            if (!$payment instanceof PaymentInterface) {
                continue;
            }

            if (BasePaymentInterface::STATE_COMPLETED !== $payment->getState()) {
                continue;
            }

            $method = $payment->getMethod();
            if (!$method instanceof PaymentMethodInterface) {
                continue;
            }

            $gatewayConfig = $method->getGatewayConfig();
            if (!$gatewayConfig instanceof GatewayConfigInterface) {
                continue;
            }

            if (SyliusPayPalExtension::PAYPAL_FACTORY_NAME !== $gatewayConfig->getFactoryName()) {
                continue;
            }

            $details = $payment->getDetails();
            if (!isset($details['paypal_order_id'])) {
                continue;
            }

            return $payment;
        }

        return null;
    }
}
