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
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\PayPalPlugin\Command\StatusPaymentRequest;
use Sylius\PayPalPlugin\CommandHandler\StatusPaymentRequestHandler;

final class StatusPaymentRequestHandlerTest extends TestCase
{
    private PaymentRequestProviderInterface&MockObject $paymentRequestProvider;

    private StateMachineInterface&MockObject $stateMachine;

    private StatusPaymentRequestHandler $handler;

    protected function setUp(): void
    {
        $this->paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);

        $this->handler = new StatusPaymentRequestHandler($this->paymentRequestProvider, $this->stateMachine);
    }

    public function test_it_completes_the_payment_request_when_the_payment_carries_a_paypal_order(): void
    {
        $paymentRequest = $this->paymentRequestFor(['paypal_order_id' => 'PAYPAL_ORDER_ID']);

        $paymentRequest->expects(self::never())->method('setResponseData');
        $this->stateMachine->expects(self::once())->method('apply')->with($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);

        ($this->handler)(new StatusPaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    public function test_it_fails_the_payment_request_when_the_payment_carries_no_paypal_order(): void
    {
        $paymentRequest = $this->paymentRequestFor([]);

        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'The payment carries no PayPal order id.']);
        $this->stateMachine->expects(self::once())->method('apply')->with($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL);

        ($this->handler)(new StatusPaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    /** @param array<string, mixed> $details */
    private function paymentRequestFor(array $details): PaymentRequestInterface&MockObject
    {
        $payment = $this->createStub(PaymentInterface::class);
        $payment->method('getDetails')->willReturn($details);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getPayment')->willReturn($payment);

        $this->paymentRequestProvider->method('provide')->willReturn($paymentRequest);

        return $paymentRequest;
    }
}
