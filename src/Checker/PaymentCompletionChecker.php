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
use Sylius\PayPalPlugin\Model\PayPalAuthorization;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;

final readonly class PaymentCompletionChecker implements PaymentCompletionCheckerInterface
{
    public function canBeCompleted(PaymentInterface $payment): bool
    {
        if (PaymentInterface::STATE_AUTHORIZED !== $payment->getState()) {
            return true;
        }

        $paymentMethod = $payment->getMethod();
        if (
            !$paymentMethod instanceof PaymentMethodInterface ||
            SyliusPayPalExtension::PAYPAL_FACTORY_NAME !== $paymentMethod->getGatewayConfig()?->getFactoryName()
        ) {
            return true;
        }

        return PayPalAuthorization::STATUS_CAPTURED === PayPalPaymentDetails::fromPayment($payment)->authorizationStatus();
    }
}
