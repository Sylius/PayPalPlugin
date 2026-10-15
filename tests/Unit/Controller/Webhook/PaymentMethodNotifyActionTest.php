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

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\PayPalPlugin\Controller\Webhook\PaymentMethodNotifyAction;
use Sylius\PayPalPlugin\Exception\PayPalWebhookEventCaptureFailedException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookEventIgnoredException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookEventTooEarlyException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookSignatureNotVerifiedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

final class PaymentMethodNotifyActionTest extends TestCase
{
    public function test_it_returns_the_response_of_the_decorated_action(): void
    {
        $response = new Response('', Response::HTTP_NO_CONTENT);
        $action = new PaymentMethodNotifyAction(
            static fn (Request $request, string $code): Response => 'PAYPAL' === $code ? $response : new Response('', Response::HTTP_INTERNAL_SERVER_ERROR),
            new NullLogger(),
        );

        self::assertSame($response, $action(new Request(), 'PAYPAL'));
    }

    public function test_it_answers_not_found_to_a_webhook_whose_signature_it_could_not_verify(): void
    {
        $action = new PaymentMethodNotifyAction(
            static fn (): Response => throw new PayPalWebhookSignatureNotVerifiedException('PAYPAL'),
            new NullLogger(),
        );

        $response = $action(new Request(), 'PAYPAL');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('{"error":"Not found"}', $response->getContent());
    }

    public function test_it_accepts_a_webhook_event_it_ignores_so_paypal_does_not_deliver_it_again(): void
    {
        $action = new PaymentMethodNotifyAction(
            static fn (): Response => throw new PayPalWebhookEventIgnoredException('ignored'),
            new NullLogger(),
        );

        self::assertSame(Response::HTTP_NO_CONTENT, $action(new Request(), 'PAYPAL')->getStatusCode());
    }

    public function test_it_asks_paypal_to_deliver_an_event_again_that_came_too_early(): void
    {
        $action = new PaymentMethodNotifyAction(
            static fn (): Response => throw new HandlerFailedException(new Envelope(new \stdClass()), [new PayPalWebhookEventTooEarlyException('CHECKOUT.ORDER.APPROVED', 540)]),
            new NullLogger(),
        );

        $response = $action(new Request(), 'PAYPAL');

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame('540', $response->headers->get('Retry-After'));
    }

    public function test_it_asks_paypal_to_deliver_an_event_again_whose_capture_failed(): void
    {
        $action = new PaymentMethodNotifyAction(
            static fn (): Response => throw new HandlerFailedException(new Envelope(new \stdClass()), [new PayPalWebhookEventCaptureFailedException('CHECKOUT.ORDER.APPROVED', '0192f0b8', 'PayPal did not capture the order.')]),
            new NullLogger(),
        );

        $response = $action(new Request(), 'PAYPAL');

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertFalse($response->headers->has('Retry-After'));
    }

    public function test_it_lets_any_other_handler_failure_through(): void
    {
        $failure = new HandlerFailedException(new Envelope(new \stdClass()), [new \RuntimeException('PayPal is down')]);
        $action = new PaymentMethodNotifyAction(static fn (): Response => throw $failure, new NullLogger());

        try {
            $action(new Request(), 'PAYPAL');
            self::fail('A handler failure must reach PayPal as an error.');
        } catch (HandlerFailedException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public function test_it_lets_any_other_failure_through(): void
    {
        $action = new PaymentMethodNotifyAction(
            static fn (): Response => throw new \RuntimeException('database is down'),
            new NullLogger(),
        );

        $this->expectExceptionMessage('database is down');

        $action(new Request(), 'PAYPAL');
    }
}
