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

namespace Tests\Sylius\PayPalPlugin\Unit\PackageTracking\Provider;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\PayPalPlugin\PackageTracking\Provider\OrderPayPalPaymentProvider;

final class OrderPayPalPaymentProviderTest extends TestCase
{
    private OrderPayPalPaymentProvider $orderPayPalPaymentProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderPayPalPaymentProvider = new OrderPayPalPaymentProvider();
    }

    #[Test]
    public function it_provides_the_last_completed_paypal_payment_when_an_earlier_attempt_was_never_captured(): void
    {
        $abandonedPayment = $this->payPalPayment('ORDER-OLD', BasePaymentInterface::STATE_NEW);
        $completedPayment = $this->payPalPayment('ORDER-NEW', BasePaymentInterface::STATE_COMPLETED);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getPayments')->willReturn(new ArrayCollection([$abandonedPayment, $completedPayment]));

        $payment = $this->orderPayPalPaymentProvider->provide($order);

        self::assertSame($completedPayment, $payment);
    }

    #[Test]
    public function it_provides_the_most_recent_one_when_several_paypal_payments_are_completed(): void
    {
        $earlierPayment = $this->payPalPayment('ORDER-OLD', BasePaymentInterface::STATE_COMPLETED);
        $latestPayment = $this->payPalPayment('ORDER-NEW', BasePaymentInterface::STATE_COMPLETED);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getPayments')->willReturn(new ArrayCollection([$earlierPayment, $latestPayment]));

        self::assertSame($latestPayment, $this->orderPayPalPaymentProvider->provide($order));
    }

    #[Test]
    public function it_provides_null_when_no_paypal_payment_is_completed(): void
    {
        $abandonedPayment = $this->payPalPayment('ORDER-OLD', BasePaymentInterface::STATE_NEW);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getPayments')->willReturn(new ArrayCollection([$abandonedPayment]));

        self::assertNull($this->orderPayPalPaymentProvider->provide($order));
    }

    #[Test]
    public function it_ignores_completed_payments_made_with_a_non_paypal_method(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn(null);

        $offlinePayment = $this->createMock(PaymentInterface::class);
        $offlinePayment->method('getState')->willReturn(BasePaymentInterface::STATE_COMPLETED);
        $offlinePayment->method('getMethod')->willReturn($paymentMethod);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getPayments')->willReturn(new ArrayCollection([$offlinePayment]));

        self::assertNull($this->orderPayPalPaymentProvider->provide($order));
    }

    private function payPalPayment(string $payPalOrderId, string $state): PaymentInterface&MockObject
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn('sylius_paypal');

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getState')->willReturn($state);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getDetails')->willReturn(['paypal_order_id' => $payPalOrderId]);

        return $payment;
    }
}
