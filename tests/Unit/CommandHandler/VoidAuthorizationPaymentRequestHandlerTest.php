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
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\VoidAuthorizationApiInterface;
use Sylius\PayPalPlugin\Command\VoidAuthorizationPaymentRequest;
use Sylius\PayPalPlugin\CommandHandler\VoidAuthorizationPaymentRequestHandler;
use Sylius\PayPalPlugin\Exception\PayPalApiConnectionException;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;

final class VoidAuthorizationPaymentRequestHandlerTest extends TestCase
{
    private PaymentRequestProviderInterface&MockObject $paymentRequestProvider;

    private OrderDetailsApiInterface&MockObject $orderDetailsApi;

    private VoidAuthorizationApiInterface&MockObject $voidAuthorizationApi;

    private StateMachineInterface&MockObject $stateMachine;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private PaymentInterface&MockObject $payment;

    /** @var list<string> */
    private array $appliedTransitions = [];

    /** @var array<string, mixed> */
    private array $details = ['paypal_order_id' => 'PAYPAL_ORDER_ID', 'authorization_id' => 'AUTHORIZATION_ID', 'authorization_status' => 'CREATED'];

    private VoidAuthorizationPaymentRequestHandler $handler;

    protected function setUp(): void
    {
        $this->paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);
        $this->orderDetailsApi = $this->createMock(OrderDetailsApiInterface::class);
        $this->voidAuthorizationApi = $this->createMock(VoidAuthorizationApiInterface::class);
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
        $this->stateMachine->method('apply')->willReturnCallback(function (object $subject, string $graph, string $transition): void {
            $this->appliedTransitions[] = $transition;
        });

        $this->handler = new VoidAuthorizationPaymentRequestHandler(
            $this->paymentRequestProvider,
            $this->orderDetailsApi,
            $this->voidAuthorizationApi,
            $this->stateMachine,
        );
    }

    public function test_it_voids_the_authorization_records_it_and_completes_the_request(): void
    {
        $this->paymentRequestIn(PaymentRequestInterface::STATE_NEW);
        $this->orderDetailsApi->method('get')->with($this->paymentMethod, 'PAYPAL_ORDER_ID')->willReturnOnConsecutiveCalls($this->orderWithAuthorization('CREATED'), $this->orderWithAuthorization('VOIDED'));

        $this->voidAuthorizationApi->expects(self::once())->method('void')->with($this->paymentMethod, 'AUTHORIZATION_ID', 'PAYMENT_REQUEST_HASH');
        $this->payment->expects(self::never())->method('getState');

        ($this->handler)(new VoidAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_PROCESS, PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
        self::assertSame('VOIDED', $this->details['authorization_status']);
    }

    public function test_it_completes_the_request_of_an_authorization_already_voided_without_asking_paypal(): void
    {
        $this->details['authorization_status'] = 'VOIDED';
        $this->paymentRequestIn(PaymentRequestInterface::STATE_PROCESSING);

        $this->orderDetailsApi->expects(self::never())->method('get');
        $this->voidAuthorizationApi->expects(self::never())->method('void');

        ($this->handler)(new VoidAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
    }

    public function test_it_records_an_authorization_paypal_has_already_voided_or_let_expire(): void
    {
        $this->paymentRequestIn(PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->expects(self::once())->method('get')->willReturn($this->orderWithAuthorization('EXPIRED'));

        $this->voidAuthorizationApi->expects(self::never())->method('void');

        ($this->handler)(new VoidAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
        self::assertSame('EXPIRED', $this->details['authorization_status']);
    }

    public function test_it_completes_the_request_when_paypal_refuses_the_void_of_an_authorization_it_has_voided_meanwhile(): void
    {
        $this->paymentRequestIn(PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->method('get')->willReturnOnConsecutiveCalls($this->orderWithAuthorization('CREATED'), $this->orderWithAuthorization('VOIDED'));
        $this->voidAuthorizationApi->method('void')->willThrowException(
            new PayPalApiRequestException('POST', 'v2/payments/authorizations/AUTHORIZATION_ID/void', 422, 'UNPROCESSABLE_ENTITY', issues: ['PREVIOUSLY_VOIDED']),
        );

        ($this->handler)(new VoidAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_COMPLETE], $this->appliedTransitions);
    }

    public function test_it_fails_the_request_with_the_reason_paypal_rejected_the_void(): void
    {
        $paymentRequest = $this->paymentRequestIn(PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->method('get')->willReturn($this->orderWithAuthorization('CREATED'));
        $this->voidAuthorizationApi->method('void')->willThrowException(
            new PayPalApiRequestException('POST', 'v2/payments/authorizations/AUTHORIZATION_ID/void', 422, 'UNPROCESSABLE_ENTITY', issues: ['AUTHORIZATION_ALREADY_CAPTURED']),
        );

        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'PayPal rejected the void: UNPROCESSABLE_ENTITY [AUTHORIZATION_ALREADY_CAPTURED]']);

        ($this->handler)(new VoidAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_FAIL], $this->appliedTransitions);
    }

    public function test_it_fails_the_request_of_an_authorization_paypal_has_captured(): void
    {
        $paymentRequest = $this->paymentRequestIn(PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->method('get')->willReturn($this->orderWithAuthorization('CAPTURED'));

        $this->voidAuthorizationApi->expects(self::never())->method('void');
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'PayPal reported the authorization as CAPTURED.']);

        ($this->handler)(new VoidAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_FAIL], $this->appliedTransitions);
    }

    public function test_it_fails_the_request_when_paypal_cannot_be_reached(): void
    {
        $paymentRequest = $this->paymentRequestIn(PaymentRequestInterface::STATE_PROCESSING);
        $this->orderDetailsApi->method('get')->willReturn($this->orderWithAuthorization('CREATED'));
        $this->voidAuthorizationApi->method('void')->willThrowException(new PayPalApiConnectionException('POST', 'v2/payments/authorizations/AUTHORIZATION_ID/void'));

        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'PayPal could not be reached to void the authorization.']);

        ($this->handler)(new VoidAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_FAIL], $this->appliedTransitions);
    }

    public function test_it_fails_the_request_of_a_payment_without_a_paypal_authorization(): void
    {
        $this->details = ['paypal_order_id' => 'PAYPAL_ORDER_ID'];
        $paymentRequest = $this->paymentRequestIn(PaymentRequestInterface::STATE_PROCESSING);

        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'The payment carries no PayPal authorization.']);

        ($this->handler)(new VoidAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([PaymentRequestTransitions::TRANSITION_FAIL], $this->appliedTransitions);
    }

    public function test_it_does_nothing_with_a_request_that_is_over(): void
    {
        $this->paymentRequestIn(PaymentRequestInterface::STATE_COMPLETED);

        $this->orderDetailsApi->expects(self::never())->method('get');

        ($this->handler)(new VoidAuthorizationPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([], $this->appliedTransitions);
    }

    private function paymentRequestIn(string $state): PaymentRequestInterface&MockObject
    {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getState')->willReturn($state);
        $paymentRequest->method('getPayment')->willReturn($this->payment);
        $paymentRequest->method('getMethod')->willReturn($this->paymentMethod);
        $paymentRequest->method('getId')->willReturn('PAYMENT_REQUEST_HASH');

        $this->paymentRequestProvider->method('provide')->willReturn($paymentRequest);

        return $paymentRequest;
    }

    /** @return array<string, mixed> */
    private function orderWithAuthorization(string $status): array
    {
        return [
            'id' => 'PAYPAL_ORDER_ID',
            'status' => 'COMPLETED',
            'purchase_units' => [['payments' => ['authorizations' => [['id' => 'AUTHORIZATION_ID', 'status' => $status]]]]],
        ];
    }
}
