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
use Sylius\PayPalPlugin\Command\AuthorizePayPalOrderPaymentRequest;
use Sylius\PayPalPlugin\CommandHandler\AuthorizePayPalOrderPaymentRequestHandler;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Exception\ThreeDSecureAuthenticationFailedException;
use Sylius\PayPalPlugin\Processor\PaymentAuthorizationProcessorInterface;
use Sylius\PayPalPlugin\Verifier\PaymentThreeDSecureVerifierInterface;

final class AuthorizePayPalOrderPaymentRequestHandlerTest extends TestCase
{
    private PaymentRequestProviderInterface&MockObject $paymentRequestProvider;

    private PaymentThreeDSecureVerifierInterface&MockObject $paymentThreeDSecureVerifier;

    private PaymentAuthorizationProcessorInterface&MockObject $paymentAuthorizationProcessor;

    private EntityManagerInterface&MockObject $paymentManager;

    private EntityManagerInterface&MockObject $paymentRequestManager;

    private StateMachineInterface&MockObject $stateMachine;

    private PaymentInterface&MockObject $payment;

    /** @var list<array{string, string}> */
    private array $appliedTransitions = [];

    private AuthorizePayPalOrderPaymentRequestHandler $handler;

    protected function setUp(): void
    {
        $this->paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);
        $this->paymentThreeDSecureVerifier = $this->createMock(PaymentThreeDSecureVerifierInterface::class);
        $this->paymentAuthorizationProcessor = $this->createMock(PaymentAuthorizationProcessorInterface::class);
        $this->paymentManager = $this->createMock(EntityManagerInterface::class);
        $this->paymentRequestManager = $this->createMock(EntityManagerInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->payment = $this->createMock(PaymentInterface::class);

        $this->stateMachine->method('apply')->willReturnCallback(function (object $subject, string $graph, string $transition): void {
            $this->appliedTransitions[] = [$graph, $transition];
        });

        $this->handler = new AuthorizePayPalOrderPaymentRequestHandler(
            $this->paymentRequestProvider,
            $this->paymentThreeDSecureVerifier,
            $this->paymentAuthorizationProcessor,
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
        $this->paymentAuthorizationProcessor->method('authorize')->willReturn($this->authorizedOrder('CREATED'));
        $this->stateMachine->method('can')->willReturn(true);

        ($this->handler)(new AuthorizePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

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

        $this->paymentAuthorizationProcessor->expects(self::never())->method('authorize');

        ($this->handler)(new AuthorizePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([], $this->appliedTransitions);
    }

    public function test_it_authorizes_the_payment_paypal_holds_the_funds_of_and_completes_the_request(): void
    {
        $this->paymentRequestWith();
        $this->paymentAuthorizationProcessor->expects(self::once())->method('authorize')->with($this->payment, 'PAYMENT_REQUEST_HASH-authorize')->willReturn($this->authorizedOrder('CREATED'));
        $this->stateMachine->method('can')->with($this->payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_AUTHORIZE)->willReturn(true);

        ($this->handler)(new AuthorizePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([
            [PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_AUTHORIZE],
            [PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE],
        ], $this->appliedTransitions);
    }

    public function test_it_moves_a_payment_with_a_pending_authorization_to_processing(): void
    {
        $this->paymentRequestWith();
        $this->paymentAuthorizationProcessor->method('authorize')->willReturn($this->authorizedOrder('PENDING'));
        $this->stateMachine->method('can')->with($this->payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS)->willReturn(true);

        ($this->handler)(new AuthorizePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([
            [PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS],
            [PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE],
        ], $this->appliedTransitions);
    }

    public function test_it_fails_the_payment_and_the_request_when_paypal_denied_the_authorization(): void
    {
        $paymentRequest = $this->paymentRequestWith();
        $this->paymentAuthorizationProcessor->method('authorize')->willReturn($this->authorizedOrder('DENIED'));
        $this->stateMachine->method('can')->with($this->payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL)->willReturn(true);

        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'PayPal reported the authorization as DENIED.']);

        ($this->handler)(new AuthorizePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([
            [PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL],
            [PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL],
        ], $this->appliedTransitions);
    }

    public function test_it_fails_the_payment_request_when_paypal_authorized_nothing(): void
    {
        $paymentRequest = $this->paymentRequestWith();
        $this->paymentAuthorizationProcessor->method('authorize')->willReturn(['id' => 'PAYPAL_ORDER_ID', 'status' => 'APPROVED']);

        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'PayPal did not authorize the order.']);

        ($this->handler)(new AuthorizePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL]], $this->appliedTransitions);
    }

    public function test_it_cancels_an_attempt_the_payer_never_approved_and_leaves_the_payment_payable(): void
    {
        $paymentRequest = $this->paymentRequestWith();
        $this->paymentAuthorizationProcessor->method('authorize')->willReturn(['id' => 'PAYPAL_ORDER_ID', 'status' => 'CREATED']);

        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'The payer did not approve the PayPal order.']);

        ($this->handler)(new AuthorizePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_CANCEL]], $this->appliedTransitions);
    }

    public function test_it_tells_the_payer_to_choose_another_payment_method_paypal_declined(): void
    {
        $paymentRequest = $this->paymentRequestWith();
        $this->paymentAuthorizationProcessor->method('authorize')->willThrowException(
            new PayPalApiRequestException('POST', 'v2/checkout/orders/PAYPAL_ORDER_ID/authorize', 422, 'UNPROCESSABLE_ENTITY', issues: ['INSTRUMENT_DECLINED']),
        );

        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'PayPal declined the payment method. Choose another one.']);

        ($this->handler)(new AuthorizePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL]], $this->appliedTransitions);
    }

    public function test_it_asks_the_payer_to_authenticate_the_card_again_when_3d_secure_did_not_finish(): void
    {
        $paymentRequest = $this->paymentRequestWith();
        $this->paymentThreeDSecureVerifier->method('verify')->willThrowException(new ThreeDSecureAuthenticationFailedException(retryable: true));

        $this->paymentAuthorizationProcessor->expects(self::never())->method('authorize');
        $paymentRequest->expects(self::once())->method('setResponseData')->with([
            'reason' => 'The 3D Secure authentication of the card did not finish.',
            'three_d_secure' => 'retry',
        ]);

        ($this->handler)(new AuthorizePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_CANCEL]], $this->appliedTransitions);
    }

    public function test_it_fails_the_payment_when_3d_secure_refused_the_card(): void
    {
        $this->paymentRequestWith();
        $this->paymentThreeDSecureVerifier->method('verify')->willThrowException(new ThreeDSecureAuthenticationFailedException(retryable: false));

        ($this->handler)(new AuthorizePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([
            [PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL],
            [PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL],
        ], $this->appliedTransitions);
    }

    public function test_it_cancels_the_request_of_a_payment_another_attempt_has_already_authorized(): void
    {
        $paymentRequest = $this->paymentRequestWith();
        $this->payment->method('getState')->willReturn(PaymentInterface::STATE_AUTHORIZED);

        $this->paymentAuthorizationProcessor->expects(self::never())->method('authorize');
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'Another PayPal order has already paid the payment.']);

        ($this->handler)(new AuthorizePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_CANCEL]], $this->appliedTransitions);
    }

    public function test_it_fails_the_request_of_a_payment_without_a_paypal_order(): void
    {
        $paymentRequest = $this->paymentRequestWith(details: []);

        $this->paymentAuthorizationProcessor->expects(self::never())->method('authorize');
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'The payment carries no PayPal order id.']);

        ($this->handler)(new AuthorizePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL]], $this->appliedTransitions);
    }

    public function test_it_does_nothing_with_a_request_that_is_not_processing(): void
    {
        $this->paymentRequestWith(state: PaymentRequestInterface::STATE_NEW);

        $this->paymentAuthorizationProcessor->expects(self::never())->method('authorize');

        ($this->handler)(new AuthorizePayPalOrderPaymentRequest('PAYMENT_REQUEST_HASH'));

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
        $paymentRequest->method('getId')->willReturn('PAYMENT_REQUEST_HASH');

        $this->paymentRequestProvider->method('provide')->willReturn($paymentRequest);

        return $paymentRequest;
    }

    /** @return array<string, mixed> */
    private function authorizedOrder(string $authorizationStatus): array
    {
        return [
            'status' => 'COMPLETED',
            'purchase_units' => [['payments' => ['authorizations' => [[
                'id' => 'AUTHORIZATION_ID',
                'status' => $authorizationStatus,
            ]]]]],
        ];
    }
}
