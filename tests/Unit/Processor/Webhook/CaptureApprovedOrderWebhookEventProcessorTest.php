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

namespace Tests\Sylius\PayPalPlugin\Unit\Processor\Webhook;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PaymentBundle\CommandProvider\PaymentRequestCommandProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\Command\AuthorizePayPalOrderPaymentRequest;
use Sylius\PayPalPlugin\Command\CapturePayPalOrderPaymentRequest;
use Sylius\PayPalPlugin\CommandProvider\CaptureAuthorizationPaymentRequestCommandProvider;
use Sylius\PayPalPlugin\Processor\Webhook\CaptureApprovedOrderWebhookEventProcessor;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class CaptureApprovedOrderWebhookEventProcessorTest extends TestCase
{
    private const HASH = '0192f0b8-4d0b-7d4a-9b5e-3c2a1f6e8d10';

    /** @var PaymentRequestRepositoryInterface<PaymentRequestInterface>&MockObject */
    private PaymentRequestRepositoryInterface&MockObject $paymentRequestRepository;

    private PaymentRequestCommandProviderInterface&MockObject $paymentRequestCommandProvider;

    private MessageBusInterface&MockObject $commandBus;

    private PaymentInterface&MockObject $payment;

    private CaptureApprovedOrderWebhookEventProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentRequestRepository = $this->createMock(PaymentRequestRepositoryInterface::class);
        $this->paymentRequestCommandProvider = $this->createMock(PaymentRequestCommandProviderInterface::class);
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->payment = $this->createMock(PaymentInterface::class);
        $this->payment->method('getState')->willReturn(PaymentInterface::STATE_PROCESSING);

        $this->processor = new CaptureApprovedOrderWebhookEventProcessor(
            $this->paymentRequestRepository,
            $this->paymentRequestCommandProvider,
            $this->commandBus,
        );
    }

    public function test_it_captures_the_order_of_the_payment_request_awaiting_it_right_away(): void
    {
        $paymentRequest = $this->capturePaymentRequest(stateAfterCapture: PaymentRequestInterface::STATE_COMPLETED);
        $command = new CapturePayPalOrderPaymentRequest(self::HASH);

        $this->paymentRequestCommandProvider->method('provide')->with($paymentRequest)->willReturn($command);
        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with($command)
            ->willReturn(new Envelope($command))
        ;

        $this->processor->process('CHECKOUT.ORDER.APPROVED', $this->approvedOrder(), $this->payment);
    }

    public function test_it_authorizes_the_order_of_the_payment_request_awaiting_its_authorization(): void
    {
        $paymentRequest = $this->capturePaymentRequest(stateAfterCapture: PaymentRequestInterface::STATE_COMPLETED, action: PaymentRequestInterface::ACTION_AUTHORIZE);
        $command = new AuthorizePayPalOrderPaymentRequest(self::HASH);

        $this->paymentRequestCommandProvider->method('provide')->with($paymentRequest)->willReturn($command);
        $this->commandBus->expects(self::once())->method('dispatch')->with($command)->willReturn(new Envelope($command));

        $this->processor->process('CHECKOUT.ORDER.APPROVED', $this->approvedOrder(), $this->payment);
    }

    public function test_it_does_nothing_for_a_payment_another_payment_request_has_already_authorized(): void
    {
        $authorizedPayment = $this->createStub(PaymentInterface::class);
        $authorizedPayment->method('getState')->willReturn(PaymentInterface::STATE_AUTHORIZED);
        $this->capturePaymentRequest(payment: $authorizedPayment, action: PaymentRequestInterface::ACTION_AUTHORIZE);

        $this->commandBus->expects(self::never())->method('dispatch');

        $this->processor->process('CHECKOUT.ORDER.APPROVED', $this->approvedOrder(), $authorizedPayment);
    }

    public function test_it_does_nothing_for_a_payment_request_of_another_action(): void
    {
        $this->capturePaymentRequest(action: CaptureAuthorizationPaymentRequestCommandProvider::ACTION);

        $this->commandBus->expects(self::never())->method('dispatch');

        $this->processor->process('CHECKOUT.ORDER.APPROVED', $this->approvedOrder(), $this->payment);
    }

    public function test_it_leaves_a_failed_capture_to_its_failed_payment_request(): void
    {
        $this->capturePaymentRequest(stateAfterCapture: PaymentRequestInterface::STATE_FAILED);
        $this->paymentRequestCommandProvider->method('provide')->willReturn(new CapturePayPalOrderPaymentRequest(self::HASH));

        $this->commandBus->expects(self::once())->method('dispatch')->willReturnCallback(static fn (object $command): Envelope => new Envelope($command));

        $this->processor->process('CHECKOUT.ORDER.APPROVED', $this->approvedOrder(), $this->payment);
    }

    public function test_it_does_nothing_once_the_payment_request_no_longer_awaits_the_capture(): void
    {
        $this->capturePaymentRequest(state: PaymentRequestInterface::STATE_COMPLETED);

        $this->commandBus->expects(self::never())->method('dispatch');

        $this->processor->process('CHECKOUT.ORDER.APPROVED', $this->approvedOrder(), $this->payment);
    }

    public function test_it_does_nothing_for_a_payment_request_of_another_payment(): void
    {
        $this->capturePaymentRequest(payment: $this->createStub(PaymentInterface::class));

        $this->commandBus->expects(self::never())->method('dispatch');

        $this->processor->process('CHECKOUT.ORDER.APPROVED', $this->approvedOrder(), $this->payment);
    }

    public function test_it_does_nothing_for_an_order_paypal_no_longer_reports_as_approved(): void
    {
        $this->paymentRequestRepository->expects(self::never())->method('find');
        $this->commandBus->expects(self::never())->method('dispatch');

        $this->processor->process('CHECKOUT.ORDER.APPROVED', ['status' => 'COMPLETED'] + $this->approvedOrder(), $this->payment);
    }

    public function test_it_does_nothing_for_an_order_paid_by_another_payment_request(): void
    {
        $this->paymentRequestRepository->method('find')->willReturn(null);

        $this->commandBus->expects(self::never())->method('dispatch');

        $this->processor->process('CHECKOUT.ORDER.APPROVED', $this->approvedOrder(), $this->payment);
    }

    private function capturePaymentRequest(
        string $state = PaymentRequestInterface::STATE_PROCESSING,
        ?PaymentInterface $payment = null,
        ?string $stateAfterCapture = null,
        string $action = PaymentRequestInterface::ACTION_CAPTURE,
    ): PaymentRequestInterface {
        $paymentRequest = $this->createStub(PaymentRequestInterface::class);
        $paymentRequest->method('getId')->willReturn(self::HASH);
        $paymentRequest->method('getAction')->willReturn($action);
        $paymentRequest->method('getState')->willReturnOnConsecutiveCalls($state, $stateAfterCapture ?? $state);
        $paymentRequest->method('getPayment')->willReturn($payment ?? $this->payment);

        $this->paymentRequestRepository->method('find')->with(self::HASH)->willReturn($paymentRequest);

        return $paymentRequest;
    }

    /** @return array<string, mixed> */
    private function approvedOrder(): array
    {
        return [
            'id' => 'PAYPAL_ORDER_ID',
            'status' => 'APPROVED',
            'purchase_units' => [['custom_id' => self::HASH]],
        ];
    }
}
