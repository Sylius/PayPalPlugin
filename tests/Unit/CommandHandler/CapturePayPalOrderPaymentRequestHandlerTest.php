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

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Command\CapturePayPalOrderPaymentRequest;
use Sylius\PayPalPlugin\CommandHandler\CapturePayPalOrderPaymentRequestHandler;
use Sylius\PayPalPlugin\Exception\PayPalApiConnectionException;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Exception\ThreeDSecureAuthenticationFailedException;
use Sylius\PayPalPlugin\Mapper\PayPalOrderDetailsMapper;
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;
use Sylius\PayPalPlugin\Processor\PaymentCaptureProcessorInterface;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Sylius\PayPalPlugin\Verifier\PaymentThreeDSecureVerifierInterface;

final class CapturePayPalOrderPaymentRequestHandlerTest extends TestCase
{
    private PaymentRequestProviderInterface&MockObject $paymentRequestProvider;

    private PaymentThreeDSecureVerifierInterface&MockObject $paymentThreeDSecureVerifier;

    private PaymentCaptureProcessorInterface&MockObject $paymentCaptureProcessor;

    private PaymentSettlementProcessorInterface&MockObject $paymentSettlementProcessor;

    private EntityManagerInterface&MockObject $paymentManager;

    private EntityManagerInterface&MockObject $paymentRequestManager;

    private StateMachineInterface&MockObject $stateMachine;

    private PaymentInterface&MockObject $payment;

    /** @var list<array{string, string}> */
    private array $appliedTransitions = [];

    private CapturePayPalOrderPaymentRequestHandler $handler;

    protected function setUp(): void
    {
        $this->paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);
        $this->paymentThreeDSecureVerifier = $this->createMock(PaymentThreeDSecureVerifierInterface::class);
        $this->paymentCaptureProcessor = $this->createMock(PaymentCaptureProcessorInterface::class);
        $this->paymentSettlementProcessor = $this->createMock(PaymentSettlementProcessorInterface::class);
        $this->paymentManager = $this->createMock(EntityManagerInterface::class);
        $this->paymentRequestManager = $this->createMock(EntityManagerInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->payment = $this->createMock(PaymentInterface::class);

        $this->stateMachine->method('apply')->willReturnCallback(function (object $subject, string $graph, string $transition): void {
            $this->appliedTransitions[] = [$graph, $transition];
        });

        $this->handler = new CapturePayPalOrderPaymentRequestHandler(
            $this->paymentRequestProvider,
            $this->paymentThreeDSecureVerifier,
            $this->paymentCaptureProcessor,
            $this->paymentSettlementProcessor,
            $this->paymentManager,
            $this->paymentRequestManager,
            $this->stateMachine,
        );
    }

    public function test_it_locks_the_payment_before_the_request(): void
    {
        $paymentRequest = $this->paymentRequestWith();
        $lockedEntities = [];
        $this->paymentManager->method('refresh')->willReturnCallback(static function (object $entity, LockMode|int|null $lockMode) use (&$lockedEntities): void {
            $lockedEntities[] = [$entity, $lockMode];
        });
        $this->paymentRequestManager->method('refresh')->willReturnCallback(static function (object $entity, LockMode|int|null $lockMode) use (&$lockedEntities): void {
            $lockedEntities[] = [$entity, $lockMode];
        });
        $this->paymentCaptureProcessor->method('capture')->willReturn($this->capturedOrder('COMPLETED'));
        $this->stateMachine->method('can')->willReturn(false);

        ($this->handler)(new CapturePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([
            [$this->payment, LockMode::PESSIMISTIC_WRITE],
            [$paymentRequest, LockMode::PESSIMISTIC_WRITE],
        ], $lockedEntities);
    }

    public function test_it_leaves_a_request_completed_while_it_waited_for_the_lock(): void
    {
        $this->payment->method('getDetails')->willReturn(['paypal_order_id' => 'PAYPAL_ORDER_ID']);
        $state = PaymentRequestInterface::STATE_PROCESSING;
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getPayment')->willReturn($this->payment);
        $paymentRequest->method('getState')->willReturnCallback(static function () use (&$state): string {
            return $state;
        });
        $this->paymentRequestProvider->method('provide')->willReturn($paymentRequest);
        $this->paymentRequestManager->method('refresh')->willReturnCallback(static function () use (&$state): void {
            $state = PaymentRequestInterface::STATE_COMPLETED;
        });

        $this->paymentCaptureProcessor->expects(self::never())->method('capture');

        ($this->handler)(new CapturePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([], $this->appliedTransitions);
    }

    public function test_it_settles_the_payment_with_the_order_paypal_captured_and_completes_the_request(): void
    {
        $this->paymentRequestWith();
        $capturedOrder = $this->capturedOrder('COMPLETED');
        $this->paymentCaptureProcessor->expects(self::once())->method('capture')->with($this->payment, 'PAYMENT_REQUEST_HASH-capture')->willReturn($capturedOrder);
        $this->stateMachine->method('can')->willReturn(false);

        $this->paymentSettlementProcessor->expects(self::once())->method('settle')->with($this->payment, $capturedOrder);

        ($this->handler)(new CapturePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE]], $this->appliedTransitions);
    }

    public function test_it_moves_a_payment_with_a_pending_capture_to_processing(): void
    {
        $this->paymentRequestWith();
        $this->paymentCaptureProcessor->method('capture')->willReturn($this->capturedOrder('PENDING'));
        $this->stateMachine->method('can')->with($this->payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS)->willReturn(true);

        ($this->handler)(new CapturePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

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

        ($this->handler)(new CapturePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL]], $this->appliedTransitions);
    }

    public function test_it_fails_the_payment_request_when_paypal_captured_nothing(): void
    {
        $paymentRequest = $this->paymentRequestWith();
        $this->paymentCaptureProcessor->method('capture')->willReturn((new PayPalOrderDetailsMapper())->map(['id' => 'PAYPAL_ORDER_ID', 'status' => 'APPROVED']));

        $this->paymentSettlementProcessor->expects(self::never())->method('settle');
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'PayPal did not capture the order.']);

        ($this->handler)(new CapturePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL]], $this->appliedTransitions);
    }

    public function test_it_cancels_an_attempt_the_payer_never_approved_and_leaves_the_payment_payable(): void
    {
        $paymentRequest = $this->paymentRequestWith();
        $this->paymentCaptureProcessor->method('capture')->willReturn((new PayPalOrderDetailsMapper())->map(['id' => 'PAYPAL_ORDER_ID', 'status' => 'CREATED']));

        $this->paymentSettlementProcessor->expects(self::never())->method('settle');
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'The payer did not approve the PayPal order.']);

        ($this->handler)(new CapturePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_CANCEL]], $this->appliedTransitions);
    }

    public function test_it_keeps_a_redirect_attempt_processing_while_the_payer_is_still_at_the_bank(): void
    {
        $paymentRequest = $this->paymentRequestWith(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'payment_source' => 'trustly']);
        $paymentRequest->method('getResponseData')->willReturn(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'payer_action_url' => 'https://paypal.test/bank']);
        $this->paymentCaptureProcessor->method('capture')->willReturn((new PayPalOrderDetailsMapper())->map(['id' => 'PAYPAL_ORDER_ID', 'status' => 'PAYER_ACTION_REQUIRED']));

        $paymentRequest->expects(self::once())->method('setResponseData')->with(['awaiting_payer' => true, 'paypal_order_id' => 'PAYPAL_ORDER_ID', 'payer_action_url' => 'https://paypal.test/bank']);

        ($this->handler)(new CapturePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([], $this->appliedTransitions);
    }

    public function test_it_cancels_a_wallet_attempt_that_still_needs_the_payer(): void
    {
        $this->paymentRequestWith();
        $this->paymentCaptureProcessor->method('capture')->willReturn((new PayPalOrderDetailsMapper())->map(['id' => 'PAYPAL_ORDER_ID', 'status' => 'PAYER_ACTION_REQUIRED']));

        ($this->handler)(new CapturePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_CANCEL]], $this->appliedTransitions);
    }

    public function test_it_sends_the_payer_back_to_retry_a_card_authentication_that_did_not_finish(): void
    {
        $paymentRequest = $this->paymentRequestWith();
        $this->paymentThreeDSecureVerifier->method('verify')->with($this->payment)->willThrowException(new ThreeDSecureAuthenticationFailedException(retryable: true));

        $this->paymentCaptureProcessor->expects(self::never())->method('capture');
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'The 3D Secure authentication of the card did not finish.', 'three_d_secure' => 'retry']);

        ($this->handler)(new CapturePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_CANCEL]], $this->appliedTransitions);
    }

    public function test_it_fails_the_payment_of_a_card_the_authentication_refused(): void
    {
        $paymentRequest = $this->paymentRequestWith();
        $this->paymentThreeDSecureVerifier->method('verify')->willThrowException(new ThreeDSecureAuthenticationFailedException(retryable: false));

        $this->paymentCaptureProcessor->expects(self::never())->method('capture');
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'The 3D Secure authentication refused the card.', 'three_d_secure' => 'declined']);

        ($this->handler)(new CapturePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([
            [PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL],
            [PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL],
        ], $this->appliedTransitions);
    }

    public function test_it_fails_the_payment_request_when_the_payment_carries_no_paypal_order(): void
    {
        $paymentRequest = $this->paymentRequestWith([]);

        $this->paymentCaptureProcessor->expects(self::never())->method('capture');
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'The payment carries no PayPal order id.']);

        ($this->handler)(new CapturePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    public function test_it_cancels_an_attempt_whose_payment_another_paypal_order_has_already_paid(): void
    {
        $paymentRequest = $this->paymentRequestWith();
        $this->payment->method('getState')->willReturn(PaymentInterface::STATE_COMPLETED);

        $this->paymentCaptureProcessor->expects(self::never())->method('capture');
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'Another PayPal order has already paid the payment.']);

        ($this->handler)(new CapturePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_CANCEL]], $this->appliedTransitions);
    }

    public function test_it_leaves_a_payment_request_that_is_not_processing_alone(): void
    {
        $this->paymentRequestWith(state: PaymentRequestInterface::STATE_COMPLETED);

        $this->paymentCaptureProcessor->expects(self::never())->method('capture');

        ($this->handler)(new CapturePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([], $this->appliedTransitions);
    }

    public function test_it_asks_for_another_payment_method_when_paypal_declines_the_instrument(): void
    {
        $paymentRequest = $this->paymentRequestWith();
        $this->paymentCaptureProcessor->method('capture')->willThrowException(
            new PayPalApiRequestException('POST', 'v2/checkout/orders/PAYPAL_ORDER_ID/capture', 422, 'UNPROCESSABLE_ENTITY', issues: ['INSTRUMENT_DECLINED']),
        );

        $this->paymentSettlementProcessor->expects(self::never())->method('settle');
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'PayPal declined the payment method. Choose another one.']);

        ($this->handler)(new CapturePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL]], $this->appliedTransitions);
    }

    public function test_it_fails_the_payment_request_when_paypal_cannot_answer(): void
    {
        $paymentRequest = $this->paymentRequestWith();
        $this->paymentCaptureProcessor->method('capture')->willThrowException(new PayPalApiConnectionException('GET', 'v2/checkout/orders/PAYPAL_ORDER_ID'));

        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'PayPal did not capture the order.']);

        ($this->handler)(new CapturePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL]], $this->appliedTransitions);
    }

    private function paymentRequestWith(
        array $details = ['paypal_order_id' => 'PAYPAL_ORDER_ID'],
        string $state = PaymentRequestInterface::STATE_PROCESSING,
    ): PaymentRequestInterface&MockObject {
        $this->payment->method('getDetails')->willReturn($details);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getState')->willReturn($state);
        $paymentRequest->method('getPayment')->willReturn($this->payment);
        $paymentRequest->method('getId')->willReturn('PAYMENT_REQUEST_HASH');

        $this->paymentRequestProvider->method('provide')->willReturn($paymentRequest);

        return $paymentRequest;
    }

    private function capturedOrder(string $captureStatus): PayPalOrderDetails
    {
        return (new PayPalOrderDetailsMapper())->map([
            'status' => 'COMPLETED',
            'purchase_units' => [['payments' => ['captures' => [[
                'id' => 'CAPTURE_ID',
                'status' => $captureStatus,
                'amount' => ['currency_code' => 'USD', 'value' => '20.00'],
            ]]]]],
        ]);
    }
}
