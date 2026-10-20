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

namespace Tests\Sylius\PayPalPlugin\Unit\Applicator;

use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ApiBundle\Exception\StateMachineTransitionFailedException;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Applicator\PaymentRefundApplicator;
use Sylius\PayPalPlugin\Dispatcher\RefundPayPalPaymentRequestDispatcherInterface;

final class PaymentRefundApplicatorTest extends TestCase
{
    public function test_it_dispatches_the_refund_of_a_completed_paypal_payment(): void
    {
        $payment = $this->payment('sylius_paypal', PaymentInterface::STATE_COMPLETED);
        $dispatcher = $this->createMock(RefundPayPalPaymentRequestDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch')->with($payment);

        self::assertSame($payment, (new PaymentRefundApplicator($dispatcher))->refund($payment));
    }

    public function test_it_refuses_a_payment_that_is_not_completed(): void
    {
        $dispatcher = $this->createMock(RefundPayPalPaymentRequestDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $this->expectException(StateMachineTransitionFailedException::class);

        (new PaymentRefundApplicator($dispatcher))->refund($this->payment('sylius_paypal', PaymentInterface::STATE_REFUNDED));
    }

    public function test_it_refuses_a_payment_of_another_gateway(): void
    {
        $dispatcher = $this->createMock(RefundPayPalPaymentRequestDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $this->expectException(StateMachineTransitionFailedException::class);

        (new PaymentRefundApplicator($dispatcher))->refund($this->payment('offline', PaymentInterface::STATE_COMPLETED));
    }

    private function payment(string $factoryName, string $state): Payment
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName($factoryName);
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);
        $payment = new Payment();
        $payment->setMethod($paymentMethod);
        $payment->setState($state);

        return $payment;
    }
}
