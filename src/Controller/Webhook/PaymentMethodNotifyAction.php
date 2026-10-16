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

namespace Sylius\PayPalPlugin\Controller\Webhook;

use Psr\Log\LoggerInterface;
use Sylius\PayPalPlugin\Exception\PayPalWebhookEventCaptureFailedException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookEventIgnoredException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookEventTooEarlyException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookSignatureNotVerifiedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

final readonly class PaymentMethodNotifyAction
{
    /** @var \Closure(Request, string): Response */
    private \Closure $decoratedAction;

    /** @param callable(Request, string): Response $decoratedAction */
    public function __construct(
        callable $decoratedAction,
        private LoggerInterface $logger,
    ) {
        $this->decoratedAction = $decoratedAction(...);
    }

    public function __invoke(Request $request, string $code): Response
    {
        try {
            return ($this->decoratedAction)($request, $code);
        } catch (PayPalWebhookSignatureNotVerifiedException $exception) {
            $this->logger->warning($exception->getMessage());

            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        } catch (PayPalWebhookEventIgnoredException $exception) {
            $this->logger->info($exception->getMessage());

            return new Response('', Response::HTTP_NO_CONTENT);
        } catch (HandlerFailedException $exception) {
            $tooEarly = $exception->getWrappedExceptions(PayPalWebhookEventTooEarlyException::class, true);
            if ([] !== $tooEarly) {
                /** @var PayPalWebhookEventTooEarlyException $tooEarlyException */
                $tooEarlyException = reset($tooEarly);
                $this->logger->info($tooEarlyException->getMessage());

                return new Response('', Response::HTTP_SERVICE_UNAVAILABLE, ['Retry-After' => (string) $tooEarlyException->getRetryAfter()]);
            }

            $captureFailed = $exception->getWrappedExceptions(PayPalWebhookEventCaptureFailedException::class, true);
            if ([] !== $captureFailed) {
                $this->logger->warning(reset($captureFailed)->getMessage());

                return new Response('', Response::HTTP_SERVICE_UNAVAILABLE);
            }

            throw $exception;
        }
    }
}
