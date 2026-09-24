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
use Sylius\PayPalPlugin\Exception\PermanentWebhookFailureInterface;
use Sylius\PayPalPlugin\Processor\Webhook\WebhookProcessorInterface;
use Sylius\PayPalPlugin\Verifier\PayPalWebhookRequestVerifierInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class PayPalWebhookAction
{
    /** @param iterable<WebhookProcessorInterface> $processors */
    public function __construct(
        private PayPalWebhookRequestVerifierInterface $requestVerifier,
        private iterable $processors,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->requestVerifier->verify($request)) {
            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        $eventType = is_array($payload) ? ($payload['event_type'] ?? null) : null;

        if (!is_string($eventType) || '' === $eventType) {
            $this->logger->warning('A verified PayPal webhook request carried no event type and was ignored.');

            return new JsonResponse([], Response::HTTP_NO_CONTENT);
        }

        $replay = false;

        foreach ($this->processors as $processor) {
            if (!$processor->supports($eventType)) {
                continue;
            }

            try {
                $processor->process($payload);
            } catch (PermanentWebhookFailureInterface $exception) {
                $this->logger->error(sprintf(
                    'Discarded the PayPal "%s" webhook, which no replay could fix: %s',
                    $eventType,
                    $exception->getMessage(),
                ));
            } catch (\Throwable $exception) {
                // Anything unrecognised is assumed to be transient. An operator can re-enable a payment
                // method or bring a database back within PayPal's replay window; a wrongly discarded event
                // about money cannot be recovered at all.
                $this->logger->critical(sprintf(
                    'Could not process the PayPal "%s" webhook and asked PayPal to deliver it again: %s',
                    $eventType,
                    $exception->getMessage(),
                ));

                $replay = true;
            }
        }

        return new JsonResponse([], $replay ? Response::HTTP_SERVICE_UNAVAILABLE : Response::HTTP_NO_CONTENT);
    }
}
