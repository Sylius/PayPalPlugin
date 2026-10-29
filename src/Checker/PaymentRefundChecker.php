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

namespace Sylius\PayPalPlugin\Checker;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;

final readonly class PaymentRefundChecker implements PaymentRefundCheckerInterface
{
    public function canBeRefunded(PaymentInterface $payment): bool
    {
        $paymentMethod = $payment->getMethod();
        if (
            !$paymentMethod instanceof PaymentMethodInterface ||
            SyliusPayPalExtension::PAYPAL_FACTORY_NAME !== $paymentMethod->getGatewayConfig()?->getFactoryName()
        ) {
            return true;
        }

        $details = PayPalPaymentDetails::fromPayment($payment);

        return !$details->hasPayPalOrderId() || $details->isRefunded();
    }
}
