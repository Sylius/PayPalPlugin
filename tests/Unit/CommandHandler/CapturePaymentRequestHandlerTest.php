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
use Sylius\PayPalPlugin\Command\CapturePaymentRequest;
use Sylius\PayPalPlugin\CommandHandler\CapturePaymentRequestHandler;
use Sylius\PayPalPlugin\Creator\PayPalOrderCreatorInterface;
use Sylius\PayPalPlugin\Exception\InvalidPayerDataException;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProvider;

final class CapturePaymentRequestHandlerTest extends TestCase
{
    private PaymentRequestProviderInterface&MockObject $paymentRequestProvider;

    private PayPalOrderCreatorInterface&MockObject $payPalOrderCreator;

    private StateMachineInterface&MockObject $stateMachine;

    private PaymentInterface&MockObject $payment;

    private CapturePaymentRequestHandler $handler;

    protected function setUp(): void
    {
        $this->paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);
        $this->payPalOrderCreator = $this->createMock(PayPalOrderCreatorInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->payment = $this->createMock(PaymentInterface::class);

        $this->handler = new CapturePaymentRequestHandler(
            $this->paymentRequestProvider,
            $this->payPalOrderCreator,
            new PayPalPaymentSourceProvider(),
            $this->stateMachine,
        );
    }

    public function test_it_creates_the_paypal_order_named_after_the_payment_request_and_processes_it(): void
    {
        $paymentRequest = $this->paymentRequestWith(['payment_source' => 'card']);

        $this->payPalOrderCreator->expects(self::once())->method('create')->with($this->payment, 'card', 'PAYMENT_REQUEST_HASH', 'PAYMENT_REQUEST_HASH')->willReturn(PayPalPaymentDetails::create()->withPayPalOrderId('PAYPAL_ORDER_ID'));
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['paypal_order_id' => 'PAYPAL_ORDER_ID']);
        $this->stateMachine->expects(self::once())->method('apply')->with($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_PROCESS);

        ($this->handler)(new CapturePaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    public function test_it_hands_the_payer_the_link_paypal_sends_them_to(): void
    {
        $paymentRequest = $this->paymentRequestWith(['payment_source' => 'trustly']);

        $this->payPalOrderCreator->method('create')->willReturn(PayPalPaymentDetails::create()->withPayPalOrderId('PAYPAL_ORDER_ID')->withPayerAction('https://www.paypal.com/payment/trustly', 'RETURN_NONCE', 'CANCEL_NONCE'));
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'payer_action_url' => 'https://www.paypal.com/payment/trustly']);

        ($this->handler)(new CapturePaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    public function test_it_waits_for_the_payer_to_choose_a_payment_source(): void
    {
        $paymentRequest = $this->paymentRequestWith(null);

        $this->payPalOrderCreator->expects(self::never())->method('create');
        $paymentRequest->expects(self::never())->method('setResponseData');
        $this->stateMachine->expects(self::never())->method('apply');

        ($this->handler)(new CapturePaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    public function test_it_leaves_a_payment_request_already_in_progress_alone(): void
    {
        $this->paymentRequestWith(['payment_source' => 'card'], PaymentRequestInterface::STATE_PROCESSING);

        $this->payPalOrderCreator->expects(self::never())->method('create');
        $this->stateMachine->expects(self::never())->method('apply');

        ($this->handler)(new CapturePaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    public function test_it_fails_the_payment_request_for_a_payment_source_paypal_does_not_support(): void
    {
        $paymentRequest = $this->paymentRequestWith(['payment_source' => 'bitcoin']);

        $this->payPalOrderCreator->expects(self::never())->method('create');
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'PayPal does not support the requested payment source.']);
        $this->stateMachine->expects(self::once())->method('apply')->with($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL);

        ($this->handler)(new CapturePaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    public function test_it_fails_the_payment_request_when_the_payer_data_does_not_fit_the_payment_source(): void
    {
        $paymentRequest = $this->paymentRequestWith(['payment_source' => 'trustly']);

        $this->payPalOrderCreator->method('create')->willThrowException(InvalidPayerDataException::withoutBillingAddress('trustly'));
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'The PayPal order needs a billing address to be paid with "trustly"']);
        $this->stateMachine->expects(self::once())->method('apply')->with($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL);

        ($this->handler)(new CapturePaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    public function test_it_fails_the_payment_request_when_paypal_does_not_create_the_order(): void
    {
        $paymentRequest = $this->paymentRequestWith(['payment_source' => 'paypal']);

        $this->payPalOrderCreator->method('create')->willReturn(null);
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'PayPal did not create the order.']);
        $this->stateMachine->expects(self::once())->method('apply')->with($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL);

        ($this->handler)(new CapturePaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    private function paymentRequestWith(mixed $payload, string $state = PaymentRequestInterface::STATE_NEW): PaymentRequestInterface&MockObject
    {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getId')->willReturn('PAYMENT_REQUEST_HASH');
        $paymentRequest->method('getState')->willReturn($state);
        $paymentRequest->method('getPayload')->willReturn($payload);
        $paymentRequest->method('getPayment')->willReturn($this->payment);

        $this->paymentRequestProvider->method('provide')->willReturn($paymentRequest);

        return $paymentRequest;
    }
}
