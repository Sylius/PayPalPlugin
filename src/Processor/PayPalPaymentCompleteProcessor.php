<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
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
use Sylius\PayPalPlugin\Payum\Request\CompleteOrder;

final class PayPalPaymentCompleteProcessor implements PaymentCompleteProcessorInterface
{
    private Payum $payum;

    public function __construct(Payum $payum)
    {
        $this->payum = $payum;
    }

    public function completePayment(PaymentInterface $payment): void
    {
        $details = $payment->getDetails();
        if (!isset($details['paypal_order_id'])) {
            return;
        }

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        $this
            ->payum
            ->getGateway($gatewayConfig->getGatewayName())
            ->execute(new CompleteOrder($payment, (string) $details['paypal_order_id']))
        ;
    }
}
