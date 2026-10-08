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

namespace Tests\Sylius\PayPalPlugin\Unit\CommandHandler;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Command\CaptureEndPaymentRequest;
use Sylius\PayPalPlugin\CommandHandler\CaptureEndPaymentRequestHandler;
use Sylius\PayPalPlugin\Processor\PaymentCaptureProcessorInterface;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;

final class CaptureEndPaymentRequestHandlerTest extends TestCase
{
    private PaymentRequestProviderInterface&MockObject $paymentRequestProvider;

    private PaymentCaptureProcessorInterface&MockObject $paymentCaptureProcessor;

    private PaymentSettlementProcessorInterface&MockObject $paymentSettlementProcessor;

    private StateMachineInterface&MockObject $stateMachine;

    private PaymentInterface&MockObject $payment;

    /** @var list<array{string, string}> */
    private array $appliedTransitions = [];

    private CaptureEndPaymentRequestHandler $handler;

    protected function setUp(): void
    {
        $this->paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);
        $this->paymentCaptureProcessor = $this->createMock(PaymentCaptureProcessorInterface::class);
        $this->paymentSettlementProcessor = $this->createMock(PaymentSettlementProcessorInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->payment = $this->createMock(PaymentInterface::class);

        $this->stateMachine->method('apply')->willReturnCallback(function (object $subject, string $graph, string $transition): void {
            $this->appliedTransitions[] = [$graph, $transition];
        });

        $this->handler = new CaptureEndPaymentRequestHandler(
            $this->paymentRequestProvider,
            $this->paymentCaptureProcessor,
            $this->paymentSettlementProcessor,
            $this->stateMachine,
        );
    }

    public function test_it_settles_the_payment_with_the_order_paypal_captured_and_completes_the_request(): void
    {
        $this->paymentRequestWith();
        $capturedOrder = $this->capturedOrder('COMPLETED');
        $this->paymentCaptureProcessor->expects(self::once())->method('capture')->with($this->payment)->willReturn($capturedOrder);
        $this->stateMachine->method('can')->willReturn(false);

        $this->paymentSettlementProcessor->expects(self::once())->method('settle')->with($this->payment, $capturedOrder);

        ($this->handler)(new CaptureEndPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE]], $this->appliedTransitions);
    }

    public function test_it_moves_a_payment_with_a_pending_capture_to_processing(): void
    {
        $this->paymentRequestWith();
        $this->paymentCaptureProcessor->method('capture')->willReturn($this->capturedOrder('PENDING'));
        $this->stateMachine->method('can')->with($this->payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS)->willReturn(true);

        ($this->handler)(new CaptureEndPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([
            [PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS],
            [PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE],
        ], $this->appliedTransitions);
    }

    public function test_it_fails_the_payment_request_when_paypal_declined_the_capture(): void
    {
        $paymentRequest = $this->paymentRequestWith();
        $this->paymentCaptureProcessor->method('capture')->willReturn($this->capturedOrder('DECLINED'));

        $this->paymentSettlementProcessor->expects(self::once())->method('settle');
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'PayPal reported the capture as DECLINED.']);

        ($this->handler)(new CaptureEndPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL]], $this->appliedTransitions);
    }

    public function test_it_fails_the_payment_request_when_paypal_captured_nothing(): void
    {
        $paymentRequest = $this->paymentRequestWith();
        $this->paymentCaptureProcessor->method('capture')->willReturn(['name' => 'UNPROCESSABLE_ENTITY']);

        $this->paymentSettlementProcessor->expects(self::never())->method('settle');
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'PayPal did not capture the order.']);

        ($this->handler)(new CaptureEndPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL]], $this->appliedTransitions);
    }

    public function test_it_fails_the_payment_request_when_the_payment_carries_no_paypal_order(): void
    {
        $paymentRequest = $this->paymentRequestWith([]);

        $this->paymentCaptureProcessor->expects(self::never())->method('capture');
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'The payment carries no PayPal order id.']);

        ($this->handler)(new CaptureEndPaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    public function test_it_leaves_a_payment_request_that_is_not_processing_alone(): void
    {
        $this->paymentRequestWith(state: PaymentRequestInterface::STATE_COMPLETED);

        $this->paymentCaptureProcessor->expects(self::never())->method('capture');

        ($this->handler)(new CaptureEndPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([], $this->appliedTransitions);
    }

    /** @param array<string, mixed> $details */
    private function paymentRequestWith(
        array $details = ['paypal_order_id' => 'PAYPAL_ORDER_ID'],
        string $state = PaymentRequestInterface::STATE_PROCESSING,
    ): PaymentRequestInterface&MockObject {
        $this->payment->method('getDetails')->willReturn($details);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getState')->willReturn($state);
        $paymentRequest->method('getPayment')->willReturn($this->payment);

        $this->paymentRequestProvider->method('provide')->willReturn($paymentRequest);

        return $paymentRequest;
    }

    /** @return array<string, mixed> */
    private function capturedOrder(string $captureStatus): array
    {
        return [
            'status' => 'COMPLETED',
            'purchase_units' => [['payments' => ['captures' => [[
                'id' => 'CAPTURE_ID',
                'status' => $captureStatus,
                'amount' => ['currency_code' => 'USD', 'value' => '20.00'],
            ]]]]],
        ];
    }
}
