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
use Sylius\PayPalPlugin\Command\CapturePayPalOrderPaymentRequest;
use Sylius\PayPalPlugin\Exception\PayPalWebhookEventCaptureFailedException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookEventTooEarlyException;
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
            600,
        );
    }

    public function test_it_captures_an_order_approved_longer_ago_than_the_payer_needs_to_come_back(): void
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

        $this->processor->process('CHECKOUT.ORDER.APPROVED', $this->approvedOrder('-11 minutes'), $this->payment);
    }

    public function test_it_leaves_a_capture_sent_to_an_asynchronous_transport_to_it(): void
    {
        $this->capturePaymentRequest(stateAfterCapture: PaymentRequestInterface::STATE_PROCESSING);
        $this->paymentRequestCommandProvider->method('provide')->willReturn(new CapturePayPalOrderPaymentRequest(self::HASH));

        $this->commandBus->expects(self::once())->method('dispatch')->willReturnCallback(static fn (object $command): Envelope => new Envelope($command));

        $this->processor->process('CHECKOUT.ORDER.APPROVED', $this->approvedOrder('-11 minutes'), $this->payment);
    }

    public function test_it_asks_for_the_event_again_when_the_capture_failed(): void
    {
        $this->capturePaymentRequest(stateAfterCapture: PaymentRequestInterface::STATE_FAILED);
        $this->paymentRequestCommandProvider->method('provide')->willReturn(new CapturePayPalOrderPaymentRequest(self::HASH));
        $this->commandBus->method('dispatch')->willReturnCallback(static fn (object $command): Envelope => new Envelope($command));

        $this->expectException(PayPalWebhookEventCaptureFailedException::class);
        $this->expectExceptionMessage('PayPal webhook event CHECKOUT.ORDER.APPROVED could not capture the order of Payment Request ' . self::HASH . ': PayPal did not capture the order.');

        $this->processor->process('CHECKOUT.ORDER.APPROVED', $this->approvedOrder('-11 minutes'), $this->payment);
    }

    public function test_it_asks_for_the_event_again_while_the_payer_may_still_be_coming_back(): void
    {
        $this->capturePaymentRequest();

        $this->commandBus->expects(self::never())->method('dispatch');

        try {
            $this->processor->process('CHECKOUT.ORDER.APPROVED', $this->approvedOrder('-1 minute'), $this->payment);
            self::fail('An approval the payer may still come back from must not be captured yet.');
        } catch (PayPalWebhookEventTooEarlyException $exception) {
            self::assertEqualsWithDelta(540, $exception->getRetryAfter(), 5);
            self::assertEqualsWithDelta(540000, $exception->getRetryDelay(), 5000);
        }
    }

    public function test_it_does_nothing_once_the_payment_request_no_longer_awaits_the_capture(): void
    {
        $this->capturePaymentRequest(state: PaymentRequestInterface::STATE_COMPLETED);

        $this->commandBus->expects(self::never())->method('dispatch');

        $this->processor->process('CHECKOUT.ORDER.APPROVED', $this->approvedOrder('-1 hour'), $this->payment);
    }

    public function test_it_does_nothing_for_a_payment_request_of_another_payment(): void
    {
        $this->capturePaymentRequest(payment: $this->createStub(PaymentInterface::class));

        $this->commandBus->expects(self::never())->method('dispatch');

        $this->processor->process('CHECKOUT.ORDER.APPROVED', $this->approvedOrder('-1 hour'), $this->payment);
    }

    public function test_it_does_nothing_for_an_order_paypal_no_longer_reports_as_approved(): void
    {
        $this->paymentRequestRepository->expects(self::never())->method('find');
        $this->commandBus->expects(self::never())->method('dispatch');

        $this->processor->process('CHECKOUT.ORDER.APPROVED', ['status' => 'COMPLETED'] + $this->approvedOrder('-1 hour'), $this->payment);
    }

    public function test_it_does_nothing_for_an_order_paid_by_another_payment_request(): void
    {
        $this->paymentRequestRepository->method('find')->willReturn(null);

        $this->commandBus->expects(self::never())->method('dispatch');

        $this->processor->process('CHECKOUT.ORDER.APPROVED', $this->approvedOrder('-1 hour'), $this->payment);
    }

    private function capturePaymentRequest(
        string $state = PaymentRequestInterface::STATE_PROCESSING,
        ?PaymentInterface $payment = null,
        ?string $stateAfterCapture = null,
    ): PaymentRequestInterface {
        $paymentRequest = $this->createStub(PaymentRequestInterface::class);
        $paymentRequest->method('getId')->willReturn(self::HASH);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_CAPTURE);
        $paymentRequest->method('getState')->willReturnOnConsecutiveCalls($state, $stateAfterCapture ?? $state);
        $paymentRequest->method('getResponseData')->willReturn(['reason' => 'PayPal did not capture the order.']);
        $paymentRequest->method('getPayment')->willReturn($payment ?? $this->payment);

        $this->paymentRequestRepository->method('find')->with(self::HASH)->willReturn($paymentRequest);

        return $paymentRequest;
    }

    /** @return array<string, mixed> */
    private function approvedOrder(string $approvedAgo): array
    {
        return [
            'id' => 'PAYPAL_ORDER_ID',
            'status' => 'APPROVED',
            'update_time' => (new \DateTimeImmutable($approvedAgo))->format(\DateTimeInterface::ATOM),
            'purchase_units' => [['custom_id' => self::HASH]],
        ];
    }
}
