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

namespace Tests\Sylius\PayPalPlugin\Unit\Controller\Webhook;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\PayPalPlugin\Controller\Webhook\PayPalWebhookAction;
use Sylius\PayPalPlugin\Processor\Webhook\WebhookProcessorInterface;
use Sylius\PayPalPlugin\Verifier\PayPalWebhookRequestVerifierInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PayPalWebhookActionTest extends TestCase
{
    private PayPalWebhookRequestVerifierInterface&MockObject $requestVerifier;

    private WebhookProcessorInterface&MockObject $refundProcessor;

    private WebhookProcessorInterface&MockObject $captureProcessor;

    private LoggerInterface&MockObject $logger;

    private PayPalWebhookAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestVerifier = $this->createMock(PayPalWebhookRequestVerifierInterface::class);
        $this->refundProcessor = $this->createMock(WebhookProcessorInterface::class);
        $this->captureProcessor = $this->createMock(WebhookProcessorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->refundProcessor
            ->method('supports')
            ->willReturnCallback(static fn (string $eventType): bool => 'PAYMENT.CAPTURE.REFUNDED' === $eventType)
        ;
        $this->captureProcessor
            ->method('supports')
            ->willReturnCallback(static fn (string $eventType): bool => 'PAYMENT.CAPTURE.COMPLETED' === $eventType)
        ;

        $this->action = new PayPalWebhookAction(
            $this->requestVerifier,
            [$this->refundProcessor, $this->captureProcessor],
            $this->logger,
        );
    }

    public function test_it_refuses_a_request_it_could_not_verify(): void
    {
        $this->requestVerifier->method('verify')->willReturn(false);

        $this->refundProcessor->expects(self::never())->method('process');

        self::assertSame(
            Response::HTTP_NOT_FOUND,
            ($this->action)($this->request('PAYMENT.CAPTURE.REFUNDED'))->getStatusCode(),
        );
    }

    public function test_it_hands_a_refund_event_to_the_refund_processor_alone(): void
    {
        $this->requestVerifier->method('verify')->willReturn(true);

        $this->refundProcessor->expects(self::once())->method('process');
        $this->captureProcessor->expects(self::never())->method('process');

        self::assertSame(
            Response::HTTP_NO_CONTENT,
            ($this->action)($this->request('PAYMENT.CAPTURE.REFUNDED'))->getStatusCode(),
        );
    }

    public function test_it_hands_a_capture_event_to_the_capture_processor_alone(): void
    {
        $this->requestVerifier->method('verify')->willReturn(true);

        $this->captureProcessor->expects(self::once())->method('process');
        $this->refundProcessor->expects(self::never())->method('process');

        ($this->action)($this->request('PAYMENT.CAPTURE.COMPLETED'));
    }

    public function test_it_accepts_an_event_nobody_handles_instead_of_inviting_three_days_of_retries(): void
    {
        $this->requestVerifier->method('verify')->willReturn(true);

        $this->refundProcessor->expects(self::never())->method('process');
        $this->captureProcessor->expects(self::never())->method('process');

        self::assertSame(
            Response::HTTP_NO_CONTENT,
            ($this->action)($this->request('CHECKOUT.ORDER.APPROVED'))->getStatusCode(),
        );
    }

    public function test_it_reports_a_verified_request_it_cannot_route(): void
    {
        $this->requestVerifier->method('verify')->willReturn(true);

        $this->logger->expects(self::exactly(2))->method('warning');

        self::assertSame(
            Response::HTTP_NO_CONTENT,
            ($this->action)(new Request([], [], [], [], [], [], 'not json'))->getStatusCode(),
        );
        self::assertSame(
            Response::HTTP_NO_CONTENT,
            ($this->action)(new Request([], [], [], [], [], [], json_encode(['resource' => []])))->getStatusCode(),
        );
    }

    public function test_it_reports_a_processor_that_blew_up_without_asking_paypal_to_retry(): void
    {
        $this->requestVerifier->method('verify')->willReturn(true);
        $this->refundProcessor->method('process')->willThrowException(new \RuntimeException('boom'));

        $this->logger->expects(self::once())->method('error');

        self::assertSame(
            Response::HTTP_NO_CONTENT,
            ($this->action)($this->request('PAYMENT.CAPTURE.REFUNDED'))->getStatusCode(),
        );
    }

    private function request(string $eventType): Request
    {
        return new Request([], [], [], [], [], [], json_encode(['event_type' => $eventType, 'resource' => []]));
    }
}
