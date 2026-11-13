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
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\PayPalPlugin\Api\CaptureAuthorizationApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Command\CaptureAuthorizationPaymentRequest;
use Sylius\PayPalPlugin\CommandHandler\CaptureAuthorizationPaymentRequestHandler;
use Sylius\PayPalPlugin\Exception\PayPalApiConnectionException;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Mapper\PayPalOrderDetailsMapper;
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;

final class CaptureAuthorizationPaymentRequestHandlerTest extends TestCase
{
    private const DETAILS = ['paypal_order_id' => 'PAYPAL_ORDER_ID', 'authorization_id' => 'AUTHORIZATION_ID', 'authorization_status' => 'CREATED'];

    private PaymentRequestProviderInterface&MockObject $paymentRequestProvider;

    private OrderDetailsApiInterface&MockObject $orderDetailsApi;

    private CaptureAuthorizationApiInterface&MockObject $captureAuthorizationApi;

    private PaymentSettlementProcessorInterface&MockObject $paymentSettlementProcessor;

    private StateMachineInterface&MockObject $stateMachine;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private PaymentInterface&MockObject $payment;

    /** @var list<string> */
    private array $appliedTransitions = [];

    /** @var array<string, mixed> */
    private array $details = self::DETAILS;

    private CaptureAuthorizationPaymentRequestHandler $handler;

    protected function setUp(): void
    {
        $this->paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);
        $this->orderDetailsApi = $this->createMock(OrderDetailsApiInterface::class);
        $this->captureAuthorizationApi = $this->createMock(CaptureAuthorizationApiInterface::class);
        $this->paymentSettlementProcessor = $this->createMock(PaymentSettlementProcessorInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->payment = $this->createMock(PaymentInterface::class);

        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn('sylius_paypal');
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $this->payment->method('getDetails')->willReturnCallback(fn (): array => $this->details);
        $this->payment->method('setDetails')->willReturnCallback(function (array $details): void {
            $this->details = $details;
        });

        $this->stateMachine->method('can')->willReturn(true);
        $this->stateMachine->method('apply')->willReturnCallback(function (object $subject, string $graph, string $transition): void {
            $this->appliedTransitions[] = $transition;
        });

        $this->handler = new CaptureAuthorizationPaymentRequestHandler(
            $this->paymentRequestProvider,
            $this->orderDetailsApi,
            $this->captureAuthorizationApi,
            $this->paymentSettlementProcessor,
            $this->stateMachine,
        );
    }

    public function test_it_captures_the_authorization_settles_the_payment_and_completes_the_request(): void
    {
        $paymentRequest = $this->paymentRequestOf(PaymentInterface::STATE_AUTHORIZED);
        $capturedOrder = $this->capturedOrder('COMPLETED');
        $this->orderDetailsApi->method('get')->with($this->paymentMethod, 'PAYPAL_ORDER_ID')->willReturnOnConsecutiveCalls($this->authorizedOrder(), $capturedOrder);

        $this->captureAuthorizationApi->expects(self::once())->method('capture')->with($this->paymentMethod, 'AUTHORIZATION_ID', 'PAYMENT_REQUEST_HASH');
        $this->paymentSettlementProcessor->expects(self::once())->method('settle')->with($this->payment, $capturedOrder);
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['capture_id' => 'CAPTURE_ID', 'capture_status' => 'COMPLETED']);

        ($this->handler)(new CaptureAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_PROCESS, PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
        self::assertSame('CAPTURED', $this->details['authorization_status']);
    }

    public function test_it_leaves_the_request_processing_while_paypal_holds_the_capture_pending(): void
    {
        $this->paymentRequestOf(PaymentInterface::STATE_AUTHORIZED, PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->method('get')->willReturnOnConsecutiveCalls($this->authorizedOrder(), $this->capturedOrder('PENDING'));

        $this->paymentSettlementProcessor->expects(self::once())->method('settle');

        ($this->handler)(new CaptureAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([], $this->appliedTransitions);
    }

    public function test_it_fails_the_request_when_paypal_declined_the_capture(): void
    {
        $paymentRequest = $this->paymentRequestOf(PaymentInterface::STATE_AUTHORIZED, PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->method('get')->willReturnOnConsecutiveCalls($this->authorizedOrder(), $this->capturedOrder('DECLINED'));

        $paymentRequest->expects(self::exactly(2))->method('setResponseData');

        ($this->handler)(new CaptureAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_FAIL], $this->appliedTransitions);
    }

    public function test_it_settles_an_authorization_paypal_has_already_captured_without_capturing_it_again(): void
    {
        $this->paymentRequestOf(PaymentInterface::STATE_AUTHORIZED, PaymentRequestInterface::STATE_PROCESSING);
        $capturedOrder = $this->capturedOrder('COMPLETED');
        $this->orderDetailsApi->expects(self::once())->method('get')->willReturn($capturedOrder);

        $this->captureAuthorizationApi->expects(self::never())->method('capture');
        $this->paymentSettlementProcessor->expects(self::once())->method('settle')->with($this->payment, $capturedOrder);

        ($this->handler)(new CaptureAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
    }

    public function test_it_reads_the_order_again_when_paypal_says_the_authorization_is_already_captured(): void
    {
        $this->paymentRequestOf(PaymentInterface::STATE_AUTHORIZED, PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->method('get')->willReturnOnConsecutiveCalls($this->authorizedOrder(), $this->capturedOrder('COMPLETED'));
        $this->captureAuthorizationApi->method('capture')->willThrowException(
            new PayPalApiRequestException('POST', 'v2/payments/authorizations/AUTHORIZATION_ID/capture', 422, 'UNPROCESSABLE_ENTITY', issues: ['AUTHORIZATION_ALREADY_CAPTURED']),
        );

        $this->paymentSettlementProcessor->expects(self::once())->method('settle');

        ($this->handler)(new CaptureAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
    }

    public function test_it_leaves_a_request_already_completed_when_the_payment_was_completed_alone(): void
    {
        $stateMachine = $this->createMock(StateMachineInterface::class);
        $stateMachine->method('can')->willReturn(false);
        $stateMachine->expects(self::never())->method('apply');
        $this->paymentRequestOf(PaymentInterface::STATE_COMPLETED, PaymentRequestInterface::STATE_PROCESSING);

        (new CaptureAuthorizationPaymentRequestHandler(
            $this->paymentRequestProvider,
            $this->orderDetailsApi,
            $this->captureAuthorizationApi,
            $this->paymentSettlementProcessor,
            $stateMachine,
        ))(new CaptureAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    public function test_it_fails_the_request_with_the_reason_paypal_rejected_the_capture(): void
    {
        $paymentRequest = $this->paymentRequestOf(PaymentInterface::STATE_AUTHORIZED, PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->method('get')->willReturn($this->authorizedOrder());
        $this->captureAuthorizationApi->method('capture')->willThrowException(
            new PayPalApiRequestException('POST', 'v2/payments/authorizations/AUTHORIZATION_ID/capture', 422, 'UNPROCESSABLE_ENTITY', issues: ['AUTHORIZATION_EXPIRED']),
        );

        $this->paymentSettlementProcessor->expects(self::never())->method('settle');
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'PayPal rejected the capture: UNPROCESSABLE_ENTITY [AUTHORIZATION_EXPIRED]']);

        ($this->handler)(new CaptureAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_FAIL], $this->appliedTransitions);
    }

    public function test_it_fails_the_request_when_paypal_cannot_be_reached(): void
    {
        $paymentRequest = $this->paymentRequestOf(PaymentInterface::STATE_AUTHORIZED, PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->method('get')->willThrowException(new PayPalApiConnectionException('GET', 'v2/checkout/orders/PAYPAL_ORDER_ID'));

        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'PayPal could not be reached to capture the payment.']);

        ($this->handler)(new CaptureAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_FAIL], $this->appliedTransitions);
    }

    public function test_it_fails_the_request_when_the_authorization_can_no_longer_be_captured(): void
    {
        $paymentRequest = $this->paymentRequestOf(PaymentInterface::STATE_AUTHORIZED, PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->method('get')->willReturn($this->authorizedOrder('VOIDED'));

        $this->captureAuthorizationApi->expects(self::never())->method('capture');
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'PayPal reported the authorization as VOIDED.']);

        ($this->handler)(new CaptureAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_FAIL], $this->appliedTransitions);
        self::assertSame('VOIDED', $this->details['authorization_status']);
    }

    public function test_it_completes_the_request_of_a_payment_that_is_already_completed(): void
    {
        $this->paymentRequestOf(PaymentInterface::STATE_COMPLETED, PaymentRequestInterface::STATE_PROCESSING);

        $this->orderDetailsApi->expects(self::never())->method('get');

        ($this->handler)(new CaptureAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
    }

    public function test_it_fails_the_request_of_a_payment_that_is_not_authorized(): void
    {
        $paymentRequest = $this->paymentRequestOf(PaymentInterface::STATE_NEW, PaymentRequestInterface::STATE_PROCESSING);

        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'The payment is not authorized.']);

        ($this->handler)(new CaptureAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_FAIL], $this->appliedTransitions);
    }

    public function test_it_fails_the_request_of_a_payment_without_a_paypal_authorization(): void
    {
        $this->details = ['paypal_order_id' => 'PAYPAL_ORDER_ID'];
        $paymentRequest = $this->paymentRequestOf(PaymentInterface::STATE_AUTHORIZED, PaymentRequestInterface::STATE_PROCESSING);

        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'The payment carries no PayPal authorization.']);

        ($this->handler)(new CaptureAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_FAIL], $this->appliedTransitions);
    }

    public function test_it_does_nothing_with_a_request_that_is_over(): void
    {
        $this->paymentRequestOf(PaymentInterface::STATE_AUTHORIZED, PaymentRequestInterface::STATE_COMPLETED);

        $this->orderDetailsApi->expects(self::never())->method('get');

        ($this->handler)(new CaptureAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([], $this->appliedTransitions);
    }

    private function paymentRequestOf(string $paymentState, string $state = PaymentRequestInterface::STATE_NEW): PaymentRequestInterface&MockObject
    {
        $this->payment->method('getState')->willReturn($paymentState);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getState')->willReturn($state);
        $paymentRequest->method('getPayment')->willReturn($this->payment);
        $paymentRequest->method('getMethod')->willReturn($this->paymentMethod);
        $paymentRequest->method('getId')->willReturn('PAYMENT_REQUEST_HASH');

        $this->paymentRequestProvider->method('provide')->willReturn($paymentRequest);

        return $paymentRequest;
    }

    private function authorizedOrder(string $status = 'CREATED'): PayPalOrderDetails
    {
        return (new PayPalOrderDetailsMapper())->map([
            'id' => 'PAYPAL_ORDER_ID',
            'status' => 'COMPLETED',
            'purchase_units' => [['payments' => ['authorizations' => [['id' => 'AUTHORIZATION_ID', 'status' => $status]]]]],
        ]);
    }

    private function capturedOrder(string $captureStatus): PayPalOrderDetails
    {
        return (new PayPalOrderDetailsMapper())->map([
            'id' => 'PAYPAL_ORDER_ID',
            'status' => 'COMPLETED',
            'purchase_units' => [['payments' => [
                'authorizations' => [['id' => 'AUTHORIZATION_ID', 'status' => 'CAPTURED']],
                'captures' => [['id' => 'CAPTURE_ID', 'status' => $captureStatus, 'amount' => ['currency_code' => 'USD', 'value' => '20.00']]],
            ]]],
        ]);
    }
}
