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

namespace Tests\Sylius\PayPalPlugin\Unit\Dispatcher;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Factory\PaymentRequestFactoryInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\Command\AuthorizePayPalOrderPaymentRequest;
use Sylius\PayPalPlugin\Command\CapturePayPalOrderPaymentRequest;
use Sylius\PayPalPlugin\Dispatcher\ApprovedPayPalOrderPaymentRequestDispatcher;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ApprovedPayPalOrderPaymentRequestDispatcherTest extends TestCase
{
    public function test_it_dispatches_the_capture_of_the_approved_paypal_order_created_with_the_capture_intent(): void
    {
        $paymentMethod = new PaymentMethod();
        $payment = new Payment();
        $payment->setMethod($paymentMethod);
        $payment->setDetails(PayPalPaymentDetails::create()->withPayPalOrderId('PAYPAL-ORDER')->withPayPalOrderIntent('CAPTURE')->toArray());
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getId')->willReturn('0199e8a4-6c1f-7a52-9a8e-5c6c2f0e3b11');

        /** @var PaymentRequestFactoryInterface<PaymentRequestInterface>&MockObject $paymentRequestFactory */
        $paymentRequestFactory = $this->createMock(PaymentRequestFactoryInterface::class);
        /** @var PaymentRequestRepositoryInterface<PaymentRequestInterface>&MockObject $paymentRequestRepository */
        $paymentRequestRepository = $this->createMock(PaymentRequestRepositoryInterface::class);
        $stateMachine = $this->createMock(StateMachineInterface::class);
        $commandBus = $this->createMock(MessageBusInterface::class);

        $paymentRequestFactory->expects(self::once())->method('create')->with($payment, $paymentMethod)->willReturn($paymentRequest);
        $paymentRequest->expects(self::once())->method('setAction')->with(PaymentRequestInterface::ACTION_CAPTURE);
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['paypal_order_id' => 'PAYPAL-ORDER']);
        $stateMachine->expects(self::once())->method('apply')->with($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_PROCESS);
        $paymentRequestRepository->expects(self::once())->method('add')->with($paymentRequest);
        $commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (CapturePayPalOrderPaymentRequest $command): bool => '0199e8a4-6c1f-7a52-9a8e-5c6c2f0e3b11' === $command->getHash()))
            ->willReturnCallback(static fn (object $command): Envelope => new Envelope($command))
        ;

        $dispatcher = new ApprovedPayPalOrderPaymentRequestDispatcher($paymentRequestFactory, $paymentRequestRepository, $stateMachine, $commandBus);

        self::assertSame($paymentRequest, $dispatcher->dispatch($payment));
    }

    public function test_it_dispatches_the_authorization_of_the_approved_paypal_order_created_with_the_authorize_intent(): void
    {
        $payment = new Payment();
        $payment->setMethod(new PaymentMethod());
        $payment->setDetails(PayPalPaymentDetails::create()->withPayPalOrderId('PAYPAL-ORDER')->withPayPalOrderIntent('AUTHORIZE')->toArray());
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getId')->willReturn('0199e8a4-6c1f-7a52-9a8e-5c6c2f0e3b11');

        /** @var PaymentRequestFactoryInterface<PaymentRequestInterface>&MockObject $paymentRequestFactory */
        $paymentRequestFactory = $this->createMock(PaymentRequestFactoryInterface::class);
        $paymentRequestFactory->method('create')->willReturn($paymentRequest);
        $commandBus = $this->createMock(MessageBusInterface::class);

        $paymentRequest->expects(self::once())->method('setAction')->with(PaymentRequestInterface::ACTION_AUTHORIZE);
        $commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (AuthorizePayPalOrderPaymentRequest $command): bool => '0199e8a4-6c1f-7a52-9a8e-5c6c2f0e3b11' === $command->getHash()))
            ->willReturnCallback(static fn (object $command): Envelope => new Envelope($command))
        ;

        $dispatcher = new ApprovedPayPalOrderPaymentRequestDispatcher(
            $paymentRequestFactory,
            $this->createMock(PaymentRequestRepositoryInterface::class),
            $this->createMock(StateMachineInterface::class),
            $commandBus,
        );

        self::assertSame($paymentRequest, $dispatcher->dispatch($payment));
    }
}
