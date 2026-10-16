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
use Psr\Container\ContainerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\PayPalPlugin\Api\WebhookResourceApiInterface;
use Sylius\PayPalPlugin\Command\NotifyPaymentRequest;
use Sylius\PayPalPlugin\CommandHandler\NotifyPaymentRequestHandler;
use Sylius\PayPalPlugin\Exception\PayPalApiServerException;
use Sylius\PayPalPlugin\Exception\PayPalWrongDataException;
use Sylius\PayPalPlugin\Model\PayPalWebhookEventStatus;
use Sylius\PayPalPlugin\Processor\Webhook\WebhookEventProcessorInterface;
use Sylius\PayPalPlugin\Repository\PayPalWebhookEventRepositoryInterface;

final class NotifyPaymentRequestHandlerTest extends TestCase
{
    private PaymentRequestProviderInterface&MockObject $paymentRequestProvider;

    private PayPalWebhookEventRepositoryInterface&MockObject $webhookEventRepository;

    private ContainerInterface&MockObject $webhookEventProcessors;

    private WebhookEventProcessorInterface&MockObject $webhookEventProcessor;

    private WebhookResourceApiInterface&MockObject $webhookResourceApi;

    private StateMachineInterface&MockObject $stateMachine;

    private PaymentMethodInterface $paymentMethod;

    private PaymentInterface $payment;

    private NotifyPaymentRequestHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);
        $this->webhookEventRepository = $this->createMock(PayPalWebhookEventRepositoryInterface::class);
        $this->webhookEventProcessors = $this->createMock(ContainerInterface::class);
        $this->webhookEventProcessor = $this->createMock(WebhookEventProcessorInterface::class);
        $this->webhookResourceApi = $this->createMock(WebhookResourceApiInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->payment = $this->createStub(PaymentInterface::class);

        $this->paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $this->paymentMethod->method('getCode')->willReturn('PAYPAL');

        $this->webhookEventProcessors->method('has')->willReturnCallback(static fn (string $eventType): bool => 'PAYMENT.CAPTURE.COMPLETED' === $eventType);
        $this->webhookEventProcessors->method('get')->willReturn($this->webhookEventProcessor);

        $this->handler = new NotifyPaymentRequestHandler(
            $this->paymentRequestProvider,
            $this->webhookEventRepository,
            $this->webhookEventProcessors,
            $this->webhookResourceApi,
            $this->stateMachine,
        );
    }

    public function test_it_processes_the_event_with_the_resource_read_again_from_paypal(): void
    {
        $paymentRequest = $this->notifyPaymentRequest('PAYMENT.CAPTURE.COMPLETED');

        $this->webhookEventRepository->expects(self::once())->method('claim')->with('WH-1', 'PAYPAL', 'PAYMENT.CAPTURE.COMPLETED')->willReturn(true);
        $this->webhookResourceApi->expects(self::once())->method('get')->with($this->paymentMethod, 'capture', 'CAPTURE_ID')->willReturn(['id' => 'CAPTURE_ID', 'status' => 'COMPLETED']);
        $this->webhookEventProcessor->expects(self::once())->method('process')->with('PAYMENT.CAPTURE.COMPLETED', ['id' => 'CAPTURE_ID', 'status' => 'COMPLETED'], $this->payment);
        $this->webhookEventRepository->expects(self::once())->method('markAs')->with('WH-1', 'PAYPAL', PayPalWebhookEventStatus::Processed);
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['webhook_event_status' => 'processed']);
        $this->stateMachine->expects(self::once())->method('apply')->with($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);

        ($this->handler)(new NotifyPaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    public function test_it_does_nothing_for_an_event_it_has_already_received(): void
    {
        $paymentRequest = $this->notifyPaymentRequest('PAYMENT.CAPTURE.COMPLETED');

        $this->webhookEventRepository->method('claim')->willReturn(false);
        $this->webhookResourceApi->expects(self::never())->method('get');
        $this->webhookEventProcessor->expects(self::never())->method('process');
        $this->webhookEventRepository->expects(self::never())->method('markAs');
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['webhook_event_status' => 'duplicate']);
        $this->stateMachine->expects(self::once())->method('apply')->with($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);

        ($this->handler)(new NotifyPaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    public function test_it_records_an_event_it_has_no_processor_for_as_ignored(): void
    {
        $paymentRequest = $this->notifyPaymentRequest('CHECKOUT.ORDER.PROCESSED');

        $this->webhookEventRepository->method('claim')->willReturn(true);
        $this->webhookResourceApi->expects(self::never())->method('get');
        $this->webhookEventRepository->expects(self::once())->method('markAs')->with('WH-1', 'PAYPAL', PayPalWebhookEventStatus::Ignored);
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['webhook_event_status' => 'ignored']);
        $this->stateMachine->expects(self::once())->method('apply')->with($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);

        ($this->handler)(new NotifyPaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    public function test_it_records_an_event_that_failed_for_good_and_fails_the_payment_request(): void
    {
        $paymentRequest = $this->notifyPaymentRequest('PAYMENT.CAPTURE.COMPLETED');

        $this->webhookEventRepository->method('claim')->willReturn(true);
        $this->webhookResourceApi->method('get')->willReturn(['id' => 'CAPTURE_ID']);
        $this->webhookEventProcessor->method('process')->willThrowException(new PayPalWrongDataException());
        $this->webhookEventRepository->expects(self::once())->method('markAs')->with('WH-1', 'PAYPAL', PayPalWebhookEventStatus::Failed);
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'PayPal data does not contain links to order']);
        $this->stateMachine->expects(self::once())->method('apply')->with($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL);

        ($this->handler)(new NotifyPaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    public function test_it_lets_a_failure_paypal_may_recover_from_through_so_the_event_is_delivered_again(): void
    {
        $this->notifyPaymentRequest('PAYMENT.CAPTURE.COMPLETED');

        $this->webhookEventRepository->method('claim')->willReturn(true);
        $this->webhookResourceApi->method('get')->willThrowException(new PayPalApiServerException('GET', 'v2/payments/captures/CAPTURE_ID', 503));
        $this->webhookEventRepository->expects(self::never())->method('markAs');
        $this->stateMachine->expects(self::never())->method('apply');

        $this->expectException(PayPalApiServerException::class);

        ($this->handler)(new NotifyPaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    public function test_it_fails_a_payment_request_that_carries_no_webhook_event(): void
    {
        $paymentRequest = $this->paymentRequestWithPayload(['http_request' => ['content' => '{"foo": "bar"}']]);

        $this->webhookEventRepository->expects(self::never())->method('claim');
        $paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'The notification carries no PayPal webhook event.']);
        $this->stateMachine->expects(self::once())->method('apply')->with($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL);

        ($this->handler)(new NotifyPaymentRequest('PAYMENT_REQUEST_HASH'));
    }

    private function notifyPaymentRequest(string $eventType): PaymentRequestInterface&MockObject
    {
        return $this->paymentRequestWithPayload(['http_request' => ['content' => (string) json_encode([
            'id' => 'WH-1',
            'event_type' => $eventType,
            'resource_type' => 'capture',
            'resource' => ['id' => 'CAPTURE_ID', 'status' => 'PENDING'],
        ])]]);
    }

    /** @param array<string, mixed> $payload */
    private function paymentRequestWithPayload(array $payload): PaymentRequestInterface&MockObject
    {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getPayload')->willReturn($payload);
        $paymentRequest->method('getPayment')->willReturn($this->payment);
        $paymentRequest->method('getMethod')->willReturn($this->paymentMethod);

        $this->paymentRequestProvider->method('provide')->willReturn($paymentRequest);

        return $paymentRequest;
    }
}
