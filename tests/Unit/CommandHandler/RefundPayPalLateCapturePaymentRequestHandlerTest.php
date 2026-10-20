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
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\Component\Payment\Model\PaymentRequest;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\RefundPaymentApiInterface;
use Sylius\PayPalPlugin\Command\RefundPayPalLateCapturePaymentRequest;
use Sylius\PayPalPlugin\CommandHandler\RefundPayPalLateCapturePaymentRequestHandler;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Generator\PayPalAuthAssertionGeneratorInterface;
use Sylius\PayPalPlugin\Processor\PayPalLateCaptureRefundConfirmationProcessorInterface;
use Sylius\PayPalPlugin\Provider\RefundReferenceNumberProviderInterface;

final class RefundPayPalLateCapturePaymentRequestHandlerTest extends TestCase
{
    private PaymentRequestProviderInterface&MockObject $paymentRequestProvider;

    private OrderDetailsApiInterface&MockObject $orderDetailsApi;

    private RefundPaymentApiInterface&MockObject $refundPaymentApi;

    private PayPalLateCaptureRefundConfirmationProcessorInterface&MockObject $lateCaptureRefundConfirmationProcessor;

    private StateMachineInterface&MockObject $stateMachine;

    /** @var list<string> */
    private array $appliedTransitions = [];

    private RefundPayPalLateCapturePaymentRequestHandler $handler;

    protected function setUp(): void
    {
        $this->paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);
        $this->orderDetailsApi = $this->createMock(OrderDetailsApiInterface::class);
        $this->refundPaymentApi = $this->createMock(RefundPaymentApiInterface::class);
        $this->lateCaptureRefundConfirmationProcessor = $this->createMock(PayPalLateCaptureRefundConfirmationProcessorInterface::class);
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
        $referenceNumberProvider = $this->createStub(RefundReferenceNumberProviderInterface::class);
        $referenceNumberProvider->method('provide')->willReturn('REFERENCE_NUMBER');

        $this->handler = new RefundPayPalLateCapturePaymentRequestHandler(
            $this->paymentRequestProvider,
            $this->orderDetailsApi,
            $this->refundPaymentApi,
            $authAssertionGenerator,
            $referenceNumberProvider,
            $this->lateCaptureRefundConfirmationProcessor,
            $this->stateMachine,
        );
    }

    public function test_it_refunds_the_late_capture_under_the_request_hash_and_records_the_refund(): void
    {
        $paymentRequest = $this->paymentRequest(['id' => 'CAPTURE_ID', 'amount' => 1539, 'currency_code' => 'EUR']);
        $this->orderDetailsApi->method('get')->with($paymentRequest->getMethod(), 'PAYPAL_ORDER_ID')->willReturn($this->orderDetails('COMPLETED'));
        $this->refundPaymentApi
            ->expects(self::once())
            ->method('refund')
            ->with($paymentRequest->getMethod(), 'CAPTURE_ID', 'AUTH_ASSERTION', 'REFERENCE_NUMBER', '15.39', 'EUR', 'PAYMENT_REQUEST_HASH')
            ->willReturn(['id' => 'REFUND_ID', 'status' => 'COMPLETED'])
        ;
        $this->lateCaptureRefundConfirmationProcessor->expects(self::once())->method('confirm')->with($paymentRequest->getPayment(), 'REFUND_ID');

        ($this->handler)(new RefundPayPalLateCapturePaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_PROCESS, PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
        self::assertSame(['refund_id' => 'REFUND_ID', 'refund_status' => 'COMPLETED'], $paymentRequest->getResponseData());
    }

    public function test_it_keeps_the_request_processing_while_paypal_has_the_refund_pending(): void
    {
        $paymentRequest = $this->paymentRequest(['id' => 'CAPTURE_ID', 'amount' => 1539, 'currency_code' => 'EUR']);
        $this->orderDetailsApi->method('get')->willReturn($this->orderDetails('COMPLETED'));
        $this->refundPaymentApi->method('refund')->willReturn(['id' => 'REFUND_ID', 'status' => 'PENDING']);
        $this->lateCaptureRefundConfirmationProcessor->expects(self::never())->method('confirm');

        ($this->handler)(new RefundPayPalLateCapturePaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_PROCESS], $this->appliedTransitions);
        self::assertSame(PaymentRequestInterface::STATE_PROCESSING, $paymentRequest->getState());
        self::assertSame(['refund_id' => 'REFUND_ID', 'refund_status' => 'PENDING'], $paymentRequest->getResponseData());
    }

    public function test_it_checks_the_paypal_order_the_late_capture_belongs_to(): void
    {
        $this->paymentRequest(['id' => 'CAPTURE_ID', 'order_id' => 'SUPERSEDED_ORDER_ID', 'amount' => 1539, 'currency_code' => 'EUR']);
        $this->orderDetailsApi->expects(self::once())->method('get')->with(self::anything(), 'SUPERSEDED_ORDER_ID')->willReturn($this->orderDetails('COMPLETED'));
        $this->refundPaymentApi->method('refund')->willReturn(['id' => 'REFUND_ID', 'status' => 'COMPLETED']);

        ($this->handler)(new RefundPayPalLateCapturePaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    public function test_it_refunds_the_amount_in_the_decimals_of_the_captured_currency(): void
    {
        $this->paymentRequest(['id' => 'CAPTURE_ID', 'amount' => 150000, 'currency_code' => 'JPY']);
        $this->orderDetailsApi->method('get')->willReturn($this->orderDetails('COMPLETED'));
        $this->refundPaymentApi
            ->expects(self::once())
            ->method('refund')
            ->with(self::anything(), 'CAPTURE_ID', 'AUTH_ASSERTION', 'REFERENCE_NUMBER', '1500', 'JPY', 'PAYMENT_REQUEST_HASH')
            ->willReturn(['id' => 'REFUND_ID', 'status' => 'COMPLETED'])
        ;

        ($this->handler)(new RefundPayPalLateCapturePaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    public function test_it_records_a_capture_paypal_already_refunded_without_refunding_it_again(): void
    {
        $paymentRequest = $this->paymentRequest(['id' => 'CAPTURE_ID', 'amount' => 1539, 'currency_code' => 'EUR']);
        $this->orderDetailsApi->method('get')->willReturn($this->orderDetails('REFUNDED'));
        $this->refundPaymentApi->expects(self::never())->method('refund');
        $this->lateCaptureRefundConfirmationProcessor->expects(self::once())->method('confirm')->with($paymentRequest->getPayment(), null);

        ($this->handler)(new RefundPayPalLateCapturePaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_PROCESS, PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
    }

    public function test_it_fails_the_request_with_the_reason_paypal_gave_for_refusing_the_refund(): void
    {
        $paymentRequest = $this->paymentRequest(['id' => 'CAPTURE_ID', 'amount' => 1539, 'currency_code' => 'EUR']);
        $this->orderDetailsApi->method('get')->willReturn($this->orderDetails('COMPLETED'));
        $this->refundPaymentApi->method('refund')->willThrowException(new PayPalApiRequestException('POST', 'v2/payments/captures/CAPTURE_ID/refund', 422, 'UNPROCESSABLE_ENTITY', debugId: 'DEBUG_ID'));
        $this->lateCaptureRefundConfirmationProcessor->expects(self::never())->method('confirm');

        ($this->handler)(new RefundPayPalLateCapturePaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_PROCESS, PaymentRequestTransitions::TRANSITION_FAIL], $this->appliedTransitions);
        self::assertSame(['reason' => 'PayPal rejected the refund: UNPROCESSABLE_ENTITY (debug id: DEBUG_ID)'], $paymentRequest->getResponseData());
    }

    public function test_it_records_a_capture_paypal_refunded_meanwhile_when_it_refuses_the_refund(): void
    {
        $paymentRequest = $this->paymentRequest(['id' => 'CAPTURE_ID', 'amount' => 1539, 'currency_code' => 'EUR']);
        $this->orderDetailsApi->method('get')->willReturn($this->orderDetails('COMPLETED'));
        $this->refundPaymentApi->method('refund')->willThrowException(new PayPalApiRequestException('POST', 'v2/payments/captures/CAPTURE_ID/refund', 422, 'UNPROCESSABLE_ENTITY', issues: ['CAPTURE_FULLY_REFUNDED']));
        $this->lateCaptureRefundConfirmationProcessor->expects(self::once())->method('confirm')->with($paymentRequest->getPayment(), null);

        ($this->handler)(new RefundPayPalLateCapturePaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_PROCESS, PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
    }

    public function test_it_fails_the_request_when_paypal_reports_the_refund_failed(): void
    {
        $paymentRequest = $this->paymentRequest(['id' => 'CAPTURE_ID', 'amount' => 1539, 'currency_code' => 'EUR']);
        $this->orderDetailsApi->method('get')->willReturn($this->orderDetails('COMPLETED'));
        $this->refundPaymentApi->method('refund')->willReturn(['id' => 'REFUND_ID', 'status' => 'FAILED']);
        $this->lateCaptureRefundConfirmationProcessor->expects(self::never())->method('confirm');

        ($this->handler)(new RefundPayPalLateCapturePaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_PROCESS, PaymentRequestTransitions::TRANSITION_FAIL], $this->appliedTransitions);
        self::assertSame(['reason' => 'PayPal reported the refund as FAILED.'], $paymentRequest->getResponseData());
    }

    public function test_it_fails_the_request_of_a_payment_without_a_late_capture(): void
    {
        $paymentRequest = $this->paymentRequest(null);
        $this->refundPaymentApi->expects(self::never())->method('refund');

        ($this->handler)(new RefundPayPalLateCapturePaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame(['reason' => 'The payment carries no late capture to refund.'], $paymentRequest->getResponseData());
    }

    public function test_it_completes_the_request_of_a_late_capture_already_refunded_without_calling_paypal(): void
    {
        $paymentRequest = $this->paymentRequest(['id' => 'CAPTURE_ID', 'amount' => 1539, 'currency_code' => 'EUR', 'refunded' => true, 'refund_id' => 'REFUND_ID']);
        $this->orderDetailsApi->expects(self::never())->method('get');
        $this->lateCaptureRefundConfirmationProcessor->expects(self::once())->method('confirm')->with($paymentRequest->getPayment(), null);
        $this->refundPaymentApi->expects(self::never())->method('refund');

        ($this->handler)(new RefundPayPalLateCapturePaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_PROCESS, PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
    }

    public function test_it_fails_the_request_of_a_payment_of_another_gateway_without_processing_it(): void
    {
        $paymentRequest = $this->paymentRequest(['id' => 'CAPTURE_ID', 'amount' => 1539, 'currency_code' => 'EUR'], 'offline');
        $this->orderDetailsApi->expects(self::never())->method('get');
        $this->refundPaymentApi->expects(self::never())->method('refund');

        ($this->handler)(new RefundPayPalLateCapturePaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_FAIL], $this->appliedTransitions);
        self::assertSame(['reason' => 'The payment was not paid with PayPal.'], $paymentRequest->getResponseData());
    }

    /** @param array<string, mixed>|null $lateCapture */
    private function paymentRequest(?array $lateCapture, string $factoryName = 'sylius_paypal'): PaymentRequestInterface
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName($factoryName);
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);
        $payment = new Payment();
        $payment->setMethod($paymentMethod);
        $payment->setDetails(null === $lateCapture
            ? ['paypal_order_id' => 'PAYPAL_ORDER_ID']
            : ['paypal_order_id' => 'PAYPAL_ORDER_ID', 'paypal_late_capture' => $lateCapture]);

        $paymentRequest = $this->getMockBuilder(PaymentRequest::class)
            ->setConstructorArgs([$payment, $paymentMethod])
            ->onlyMethods(['getId'])
            ->getMock()
        ;
        $paymentRequest->method('getId')->willReturn('PAYMENT_REQUEST_HASH');
        $paymentRequest->setAction('refund_late_capture');
        $paymentRequest->setState(PaymentRequestInterface::STATE_NEW);

        $this->paymentRequestProvider->method('provide')->willReturn($paymentRequest);

        return $paymentRequest;
    }

    /** @return array<string, mixed> */
    private function orderDetails(string $captureStatus): array
    {
        return ['purchase_units' => [['payments' => ['captures' => [['id' => 'CAPTURE_ID', 'status' => $captureStatus]]]]]];
    }
}
