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

namespace Tests\Sylius\PayPalPlugin\Unit;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Exception\PaymentAmountMismatchException;
use Sylius\PayPalPlugin\Verifier\PaymentAmountVerifier;

final class PaymentAmountVerifierTest extends TestCase
{
    private PaymentAmountVerifier $verifier;

    protected function setUp(): void
    {
        $this->verifier = new PaymentAmountVerifier();
    }

    public function testVerifySucceedsWhenAmountsMatch(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $order->method('getTotal')->willReturn(1500);

        $paypalOrderDetails = [
            'purchase_units' => [
                ['amount' => ['value' => '10.00']],
                ['amount' => ['value' => '5.00']],
            ],
        ];

        $this->verifier->verify($payment, $paypalOrderDetails);

        $this->addToAssertionCount(1);
    }

    public function testVerifySucceedsWhenAmountsMatchWithRoundedValue(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $order->method('getTotal')->willReturn(225845);

        $paypalOrderDetails = [
            'purchase_units' => [
                ['amount' => ['value' => '2258.45']],
            ],
        ];

        $this->verifier->verify($payment, $paypalOrderDetails);

        $this->addToAssertionCount(1);
    }

    public function testVerifyThrowsExceptionWhenAmountsDoNotMatch(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $order->method('getTotal')->willReturn(1000);

        $paypalOrderDetails = [
            'purchase_units' => [
                ['amount' => ['value' => '10.00']],
                ['amount' => ['value' => '5.00']],
            ],
        ];

        $this->expectException(PaymentAmountMismatchException::class);

        $this->verifier->verify($payment, $paypalOrderDetails);
    }

    public function testVerifyWithEmptyPurchaseUnitsShouldCompareToZero(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $order->method('getTotal')->willReturn(0);

        $paypalOrderDetails = [
            'purchase_units' => [],
        ];

        $this->verifier->verify($payment, $paypalOrderDetails);

        $this->addToAssertionCount(1);
    }

    public function testVerifyWithMissingPurchaseUnitsShouldCompareToPaymentDetailsAmount(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $order->method('getTotal')->willReturn(2000);
        $payment->method('getDetails')->willReturn(['payment_amount' => 2000]);

        $paypalOrderDetails = [];

        $this->verifier->verify($payment, $paypalOrderDetails);

        $this->addToAssertionCount(1);
    }

    public function testVerifyWithMissingPaymentAmountInDetailsShouldDefaultToZero(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $order->method('getTotal')->willReturn(0);
        $payment->method('getDetails')->willReturn([]);

        $paypalOrderDetails = [];

        $this->verifier->verify($payment, $paypalOrderDetails);

        $this->addToAssertionCount(1);
    }

    public function testVerifyWithMissingAmountInPurchaseUnitShouldDefaultToZero(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $order->method('getTotal')->willReturn(1000);

        $paypalOrderDetails = [
            'purchase_units' => [
                ['amount' => ['value' => '10.00']],
                ['amount' => []],
            ],
        ];

        $this->verifier->verify($payment, $paypalOrderDetails);

        $this->addToAssertionCount(1);
    }
}