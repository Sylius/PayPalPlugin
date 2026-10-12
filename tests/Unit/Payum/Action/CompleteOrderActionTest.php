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

namespace Tests\Sylius\PayPalPlugin\Unit\Payum\Action;

use Payum\Core\Action\ActionInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Payum\Action\CompleteOrderAction;
use Sylius\PayPalPlugin\Payum\Request\CompleteOrder;
use Sylius\PayPalPlugin\Processor\PaymentCaptureProcessorInterface;

final class CompleteOrderActionTest extends TestCase
{
    private PaymentCaptureProcessorInterface&MockObject $paymentCaptureProcessor;

    private LoggerInterface&MockObject $logger;

    private CompleteOrderAction $completeOrderAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentCaptureProcessor = $this->createMock(PaymentCaptureProcessorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->completeOrderAction = new CompleteOrderAction($this->paymentCaptureProcessor, $this->logger);
    }

    public function test_it_implements_action_interface(): void
    {
        self::assertInstanceOf(ActionInterface::class, $this->completeOrderAction);
    }

    public function test_it_captures_the_paypal_order_of_the_payment(): void
    {
        $payment = $this->paymentWith(['paypal_order_id' => '123123', 'payment_source' => 'card']);

        $this->paymentCaptureProcessor->expects(self::once())->method('capture')->with($payment);

        $this->completeOrderAction->execute($this->completeOrderOf($payment));
    }

    public function test_it_never_captures_an_order_paypal_completes_on_payment_approval(): void
    {
        $payment = $this->paymentWith(['paypal_order_id' => '123123', 'payment_source' => 'trustly']);

        $this->paymentCaptureProcessor->expects(self::never())->method('capture');
        $this->logger->expects(self::once())->method('warning');

        $this->completeOrderAction->execute($this->completeOrderOf($payment));
    }

    public function test_it_supports_complete_order_request_with_payment_as_model(): void
    {
        self::assertTrue($this->completeOrderAction->supports($this->completeOrderOf($this->createMock(PaymentInterface::class))));
    }

    /** @param array<string, mixed> $details */
    private function paymentWith(array $details): PaymentInterface&MockObject
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn($details);

        return $payment;
    }

    private function completeOrderOf(PaymentInterface $payment): CompleteOrder&MockObject
    {
        $request = $this->createMock(CompleteOrder::class);
        $request->method('getModel')->willReturn($payment);

        return $request;
    }
}
