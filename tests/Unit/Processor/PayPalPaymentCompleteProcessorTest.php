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

namespace Tests\Sylius\PayPalPlugin\Unit\Processor;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Processor\PaymentCaptureProcessorInterface;
use Sylius\PayPalPlugin\Processor\PaymentCompleteProcessorInterface;
use Sylius\PayPalPlugin\Processor\PayPalPaymentCompleteProcessor;

final class PayPalPaymentCompleteProcessorTest extends TestCase
{
    private PaymentCaptureProcessorInterface&MockObject $paymentCaptureProcessor;

    private LoggerInterface&MockObject $logger;

    private PayPalPaymentCompleteProcessor $paypalPaymentCompleteProcessor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentCaptureProcessor = $this->createMock(PaymentCaptureProcessorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->paypalPaymentCompleteProcessor = new PayPalPaymentCompleteProcessor($this->paymentCaptureProcessor, $this->logger);
    }

    public function test_it_implements_payment_complete_processor_interface(): void
    {
        self::assertInstanceOf(PaymentCompleteProcessorInterface::class, $this->paypalPaymentCompleteProcessor);
    }

    public function test_it_captures_the_paypal_order_of_the_payment(): void
    {
        $payment = $this->paymentWith(['paypal_order_id' => '123123', 'payment_source' => 'card']);

        $this->paymentCaptureProcessor->expects(self::once())->method('capture')->with($payment);

        $this->paypalPaymentCompleteProcessor->completePayment($payment);
    }

    public function test_it_does_nothing_if_payment_has_no_paypal_order_id_set(): void
    {
        $this->paymentCaptureProcessor->expects(self::never())->method('capture');

        $this->paypalPaymentCompleteProcessor->completePayment($this->paymentWith([]));
    }

    public function test_it_never_captures_an_order_paypal_completes_on_payment_approval(): void
    {
        $this->paymentCaptureProcessor->expects(self::never())->method('capture');
        $this->logger->expects(self::once())->method('warning');

        $this->paypalPaymentCompleteProcessor->completePayment($this->paymentWith(['paypal_order_id' => '123123', 'payment_source' => 'trustly']));
    }

    /** @param array<string, mixed> $details */
    private function paymentWith(array $details): PaymentInterface&MockObject
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn($details);

        return $payment;
    }
}
