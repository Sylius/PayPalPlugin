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
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\Component\Payment\Model\PaymentRequest;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\RefundPaymentApiInterface;
use Sylius\PayPalPlugin\Command\RefundPayPalPaymentRequest;
use Sylius\PayPalPlugin\CommandHandler\RefundPayPalPaymentRequestHandler;
use Sylius\PayPalPlugin\Exception\PayPalApiConnectionException;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Generator\PayPalAuthAssertionGeneratorInterface;
use Sylius\PayPalPlugin\Processor\PayPalPaymentRefundConfirmationProcessorInterface;
use Sylius\PayPalPlugin\Provider\RefundReferenceNumberProviderInterface;

final class RefundPayPalPaymentRequestHandlerTest extends TestCase
{
    private PaymentRequestProviderInterface&MockObject $paymentRequestProvider;

    private OrderDetailsApiInterface&MockObject $orderDetailsApi;

    private RefundPaymentApiInterface&MockObject $refundPaymentApi;

    private PayPalPaymentRefundConfirmationProcessorInterface&MockObject $refundConfirmationProcessor;

    private StateMachineInterface&MockObject $stateMachine;

    private PaymentMethod $paymentMethod;

    private Payment $payment;

    /** @var list<string> */
    private array $appliedTransitions = [];

    private RefundPayPalPaymentRequestHandler $handler;

    protected function setUp(): void
    {
        $this->paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);
        $this->orderDetailsApi = $this->createMock(OrderDetailsApiInterface::class);
        $this->refundPaymentApi = $this->createMock(RefundPaymentApiInterface::class);
        $this->refundConfirmationProcessor = $this->createMock(PayPalPaymentRefundConfirmationProcessorInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);

        $this->stateMachine->method('apply')->willReturnCallback(function (object $subject, string $graph, string $transition): void {
            $this->appliedTransitions[] = $transition;
            if ($subject instanceof PaymentRequestInterface) {
                $subject->setState([
                    PaymentRequestTransitions::TRANSITION_PROCESS => PaymentRequestInterface::STATE_PROCESSING,
                    PaymentRequestTransitions::TRANSITION_COMPLETE => PaymentRequestInterface::STATE_COMPLETED,
                    PaymentRequestTransitions::TRANSITION_FAIL => PaymentRequestInterface::STATE_FAILED,
                ][$transition]);
            }
        });

        $authAssertionGenerator = $this->createStub(PayPalAuthAssertionGeneratorInterface::class);
        $authAssertionGenerator->method('generate')->willReturn('AUTH_ASSERTION');
        $refundReferenceNumberProvider = $this->createStub(RefundReferenceNumberProviderInterface::class);
        $refundReferenceNumberProvider->method('provide')->willReturn('REFERENCE_NUMBER');

        $order = new Order();
        $order->setCurrencyCode('USD');
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius_paypal');
        $this->paymentMethod = new PaymentMethod();
        $this->paymentMethod->setGatewayConfig($gatewayConfig);
        $this->payment = new Payment();
        $this->payment->setOrder($order);
        $this->payment->setAmount(1050);
        $this->payment->setDetails(['paypal_order_id' => 'PAYPAL_ORDER_ID']);

        $this->handler = new RefundPayPalPaymentRequestHandler(
            $this->paymentRequestProvider,
            $this->orderDetailsApi,
            $this->refundPaymentApi,
            $authAssertionGenerator,
            $refundReferenceNumberProvider,
            $this->refundConfirmationProcessor,
            $this->stateMachine,
        );
    }

    public function test_it_refunds_the_capture_under_the_request_hash_and_confirms_the_completed_refund(): void
    {
        $paymentRequest = $this->paymentRequest(PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->method('get')->with($this->paymentMethod, 'PAYPAL_ORDER_ID')->willReturn($this->orderWithCapture('COMPLETED'));
        $this->refundPaymentApi
            ->expects(self::once())
            ->method('refund')
            ->with($this->paymentMethod, 'CAPTURE_ID', 'AUTH_ASSERTION', 'REFERENCE_NUMBER', '10.50', 'USD', 'PAYMENT_REQUEST_HASH')
            ->willReturn(['id' => 'REFUND_ID', 'status' => 'COMPLETED'])
        ;
        $this->refundConfirmationProcessor->expects(self::once())->method('confirm')->with($this->payment, 'REFUND_ID');

        ($this->handler)(new RefundPayPalPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
        self::assertSame(['refund_id' => 'REFUND_ID', 'refund_status' => 'COMPLETED'], $paymentRequest->getResponseData());
    }

    public function test_it_processes_a_new_request_before_refunding(): void
    {
        $this->paymentRequest(PaymentRequestInterface::STATE_NEW);
        $this->orderDetailsApi->method('get')->willReturn($this->orderWithCapture('COMPLETED'));
        $this->refundPaymentApi->method('refund')->willReturn(['id' => 'REFUND_ID', 'status' => 'COMPLETED']);

        ($this->handler)(new RefundPayPalPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_PROCESS, PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
    }

    public function test_it_keeps_the_request_processing_while_paypal_has_the_refund_pending(): void
    {
        $paymentRequest = $this->paymentRequest(PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->method('get')->willReturn($this->orderWithCapture('COMPLETED'));
        $this->refundPaymentApi->method('refund')->willReturn(['id' => 'REFUND_ID', 'status' => 'PENDING']);
        $this->refundConfirmationProcessor->expects(self::never())->method('confirm');

        ($this->handler)(new RefundPayPalPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([], $this->appliedTransitions);
        self::assertSame(PaymentRequestInterface::STATE_PROCESSING, $paymentRequest->getState());
        self::assertSame(['refund_id' => 'REFUND_ID', 'refund_status' => 'PENDING'], $paymentRequest->getResponseData());
    }

    public function test_it_fails_the_request_when_paypal_reports_the_refund_as_failed(): void
    {
        $paymentRequest = $this->paymentRequest(PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->method('get')->willReturn($this->orderWithCapture('COMPLETED'));
        $this->refundPaymentApi->method('refund')->willReturn(['id' => 'REFUND_ID', 'status' => 'FAILED']);
        $this->refundConfirmationProcessor->expects(self::never())->method('confirm');

        ($this->handler)(new RefundPayPalPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_FAIL], $this->appliedTransitions);
        self::assertSame(['reason' => 'PayPal reported the refund as FAILED.'], $paymentRequest->getResponseData());
    }

    public function test_it_fails_the_request_with_the_reason_paypal_gave_for_rejecting_the_refund(): void
    {
        $paymentRequest = $this->paymentRequest(PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->method('get')->willReturn($this->orderWithCapture('COMPLETED'));
        $this->refundPaymentApi->method('refund')->willThrowException(new PayPalApiRequestException(
            'POST',
            'v2/payments/captures/CAPTURE_ID/refund',
            422,
            'UNPROCESSABLE_ENTITY',
            'The requested action could not be performed.',
            ['REFUND_AMOUNT_EXCEEDED'],
            'DEBUG_ID',
            issueDescriptions: ['The refund amount must be less than or equal to the capture amount that has not yet been refunded.'],
        ));
        $this->refundConfirmationProcessor->expects(self::never())->method('confirm');

        ($this->handler)(new RefundPayPalPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_FAIL], $this->appliedTransitions);
        self::assertSame(
            ['reason' => 'PayPal rejected the refund: UNPROCESSABLE_ENTITY The refund amount must be less than or equal to the capture amount that has not yet been refunded. [REFUND_AMOUNT_EXCEEDED] (debug id: DEBUG_ID)'],
            $paymentRequest->getResponseData(),
        );
    }

    public function test_it_fails_the_request_when_paypal_cannot_be_reached(): void
    {
        $paymentRequest = $this->paymentRequest(PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->method('get')->willThrowException(new PayPalApiConnectionException('GET', 'v2/checkout/orders/PAYPAL_ORDER_ID'));
        $this->refundPaymentApi->expects(self::never())->method('refund');

        ($this->handler)(new RefundPayPalPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_FAIL], $this->appliedTransitions);
        self::assertSame(['reason' => 'PayPal could not be reached to refund the payment.'], $paymentRequest->getResponseData());
    }

    public function test_it_confirms_a_capture_paypal_has_already_refunded_without_refunding_it_again(): void
    {
        $this->paymentRequest(PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->method('get')->willReturn($this->orderWithCapture('REFUNDED'));
        $this->refundPaymentApi->expects(self::never())->method('refund');
        $this->refundConfirmationProcessor->expects(self::once())->method('confirm')->with($this->payment, null);

        ($this->handler)(new RefundPayPalPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
    }

    public function test_it_confirms_the_refund_when_paypal_says_the_capture_is_fully_refunded(): void
    {
        $this->paymentRequest(PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->method('get')->willReturn($this->orderWithCapture('COMPLETED'));
        $this->refundPaymentApi->method('refund')->willThrowException(new PayPalApiRequestException(
            'POST',
            'v2/payments/captures/CAPTURE_ID/refund',
            422,
            issues: ['CAPTURE_FULLY_REFUNDED'],
        ));
        $this->refundConfirmationProcessor->expects(self::once())->method('confirm')->with($this->payment, null);

        ($this->handler)(new RefundPayPalPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
    }

    public function test_it_finishes_the_request_without_calling_paypal_when_the_refund_is_already_confirmed(): void
    {
        $this->payment->setDetails(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'refunded' => true, 'refund_id' => 'REFUND_ID']);
        $this->paymentRequest(PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->expects(self::never())->method('get');
        $this->refundConfirmationProcessor->expects(self::once())->method('confirm')->with($this->payment, 'REFUND_ID');

        ($this->handler)(new RefundPayPalPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
    }

    public function test_it_fails_the_request_when_the_paypal_order_has_no_capture(): void
    {
        $paymentRequest = $this->paymentRequest(PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->method('get')->willReturn(['id' => 'PAYPAL_ORDER_ID', 'status' => 'APPROVED']);
        $this->refundPaymentApi->expects(self::never())->method('refund');

        ($this->handler)(new RefundPayPalPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame(['reason' => 'The PayPal order has no capture to refund.'], $paymentRequest->getResponseData());
    }

    public function test_it_fails_the_request_when_the_payment_carries_no_paypal_order_id(): void
    {
        $this->payment->setDetails([]);
        $paymentRequest = $this->paymentRequest(PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->expects(self::never())->method('get');

        ($this->handler)(new RefundPayPalPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame(['reason' => 'The payment carries no PayPal order id.'], $paymentRequest->getResponseData());
    }

    public function test_it_does_nothing_with_a_finished_request(): void
    {
        $this->paymentRequest(PaymentRequestInterface::STATE_FAILED);
        $this->orderDetailsApi->expects(self::never())->method('get');

        ($this->handler)(new RefundPayPalPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([], $this->appliedTransitions);
    }

    public function test_it_fails_the_request_of_a_payment_of_another_gateway_without_processing_it(): void
    {
        $this->paymentMethod->getGatewayConfig()->setFactoryName('offline');
        $paymentRequest = $this->paymentRequest(PaymentRequestInterface::STATE_NEW);
        $this->orderDetailsApi->expects(self::never())->method('get');
        $this->refundPaymentApi->expects(self::never())->method('refund');

        ($this->handler)(new RefundPayPalPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_FAIL], $this->appliedTransitions);
        self::assertSame(['reason' => 'Payment method mismatch.'], $paymentRequest->getResponseData());
    }

    private function paymentRequest(string $state): PaymentRequestInterface
    {
        $paymentRequest = $this->getMockBuilder(PaymentRequest::class)
            ->setConstructorArgs([$this->payment, $this->paymentMethod])
            ->onlyMethods(['getId'])
            ->getMock()
        ;
        $paymentRequest->method('getId')->willReturn('PAYMENT_REQUEST_HASH');
        $paymentRequest->setAction(PaymentRequestInterface::ACTION_REFUND);
        $paymentRequest->setState($state);

        $this->paymentRequestProvider->method('provide')->willReturn($paymentRequest);

        return $paymentRequest;
    }

    /** @return array<string, mixed> */
    private function orderWithCapture(string $status): array
    {
        return [
            'id' => 'PAYPAL_ORDER_ID',
            'purchase_units' => [['payments' => ['captures' => [[
                'id' => 'CAPTURE_ID',
                'status' => $status,
                'amount' => ['value' => '10.50', 'currency_code' => 'USD'],
            ]]]]],
        ];
    }
}
