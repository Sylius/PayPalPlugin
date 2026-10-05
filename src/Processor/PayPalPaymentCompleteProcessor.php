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

namespace Sylius\PayPalPlugin\Processor;

use Payum\Core\Model\GatewayConfigInterface;
use Payum\Core\Payum;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Payum\Request\CompleteOrder;

final readonly class PayPalPaymentCompleteProcessor implements PaymentCompleteProcessorInterface
{
    public function __construct(private Payum $payum)
    {
    }

    public function completePayment(PaymentInterface $payment): void
    {
        $payPalOrderId = PayPalPaymentDetails::fromPayment($payment)->orderId();
        if (null === $payPalOrderId) {
            return;
        }

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        $this
            ->payum
            ->getGateway($gatewayConfig->getGatewayName())
            ->execute(new CompleteOrder($payment, $payPalOrderId))
        ;
    }
}
