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

namespace Tests\Sylius\PayPalPlugin\Unit\Resolver;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Creator\PayPalOrderCreatorInterface;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolver;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolverInterface;

final class CapturePaymentResolverTest extends TestCase
{
    private PayPalOrderCreatorInterface&MockObject $payPalOrderCreator;

    private CapturePaymentResolver $capturePaymentResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payPalOrderCreator = $this->createMock(PayPalOrderCreatorInterface::class);

        $this->capturePaymentResolver = new CapturePaymentResolver($this->payPalOrderCreator);
    }

    public function test_it_is_a_capture_payment_resolver(): void
    {
        self::assertInstanceOf(CapturePaymentResolverInterface::class, $this->capturePaymentResolver);
    }

    public function test_it_creates_the_paypal_order_for_the_payment_source_recorded_on_the_payment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn(['payment_source' => 'venmo']);

        $this->payPalOrderCreator->expects(self::once())->method('create')->with($payment, 'venmo');

        $this->capturePaymentResolver->resolve($payment);
    }

    public function test_it_creates_a_paypal_wallet_order_when_the_payment_records_no_payment_source(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn([]);

        $this->payPalOrderCreator->expects(self::once())->method('create')->with($payment, 'paypal');

        $this->capturePaymentResolver->resolve($payment);
    }
}
