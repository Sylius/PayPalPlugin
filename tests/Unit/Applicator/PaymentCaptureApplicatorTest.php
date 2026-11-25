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
use Sylius\PayPalPlugin\Applicator\PaymentCaptureApplicator;
use Sylius\PayPalPlugin\Dispatcher\CaptureAuthorizationPaymentRequestDispatcherInterface;

final class PaymentCaptureApplicatorTest extends TestCase
{
    public function test_it_dispatches_the_capture_of_an_authorized_paypal_payment(): void
    {
        $payment = $this->payment('sylius_paypal', PaymentInterface::STATE_AUTHORIZED);
        $dispatcher = $this->createMock(CaptureAuthorizationPaymentRequestDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch')->with($payment);

        self::assertSame($payment, (new PaymentCaptureApplicator($dispatcher))->capture($payment));
    }

    public function test_it_refuses_a_payment_that_is_not_authorized(): void
    {
        $dispatcher = $this->createMock(CaptureAuthorizationPaymentRequestDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $this->expectException(StateMachineTransitionFailedException::class);
        $this->expectExceptionMessage('Only an authorized PayPal payment can be captured through PayPal, the payment is "completed".');

        (new PaymentCaptureApplicator($dispatcher))->capture($this->payment('sylius_paypal', PaymentInterface::STATE_COMPLETED));
    }

    public function test_it_refuses_a_payment_of_another_gateway(): void
    {
        $dispatcher = $this->createMock(CaptureAuthorizationPaymentRequestDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $this->expectException(StateMachineTransitionFailedException::class);
        $this->expectExceptionMessage('The payment was not paid with PayPal.');

        (new PaymentCaptureApplicator($dispatcher))->capture($this->payment('offline', PaymentInterface::STATE_AUTHORIZED));
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
