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

namespace Sylius\PayPalPlugin\Client;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class RetryingHttpClient implements ClientInterface
{
    private const RETRYABLE_STATUS_CODES = [
        Response::HTTP_TOO_MANY_REQUESTS,
        Response::HTTP_INTERNAL_SERVER_ERROR,
        Response::HTTP_BAD_GATEWAY,
        Response::HTTP_SERVICE_UNAVAILABLE,
        Response::HTTP_GATEWAY_TIMEOUT,
    ];

    private const INITIAL_RETRY_DELAY_IN_MILLISECONDS = 250;

    private const MAX_RETRY_DELAY_IN_MILLISECONDS = 2000;

    public function __construct(
        private ClientInterface $client,
        private LoggerInterface $logger,
        private ClockInterface $clock,
        private int $maxAttempts,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        for ($attempt = 1; ; ++$attempt) {
            $this->rewindBody($request);

            try {
                $response = $this->client->sendRequest($request);
            } catch (NetworkExceptionInterface $exception) {
                if ($attempt >= $this->maxAttempts) {
                    throw $exception;
                }

                $this->retryAfter($request, $attempt, $exception->getMessage(), $this->backoffDelay($attempt));

                continue;
            }

            if ($attempt >= $this->maxAttempts || !in_array($response->getStatusCode(), self::RETRYABLE_STATUS_CODES, true)) {
                return $response;
            }

            $delay = $this->retryDelay($response, $attempt);
            if (null === $delay) {
                return $response;
            }

            $this->retryAfter($request, $attempt, sprintf('HTTP %d', $response->getStatusCode()), $delay);
        }
    }

    private function retryDelay(ResponseInterface $response, int $attempt): ?float
    {
        $retryAfter = $response->getHeaderLine('Retry-After');
        if (!ctype_digit($retryAfter)) {
            return $this->backoffDelay($attempt);
        }

        $delay = (int) $retryAfter * 1000;

        return $delay > self::MAX_RETRY_DELAY_IN_MILLISECONDS ? null : $delay / 1000;
    }

    private function backoffDelay(int $attempt): float
    {
        $maxDelay = min(self::MAX_RETRY_DELAY_IN_MILLISECONDS, self::INITIAL_RETRY_DELAY_IN_MILLISECONDS * 2 ** ($attempt - 1));

        return random_int(0, $maxDelay) / 1000;
    }

    private function retryAfter(RequestInterface $request, int $attempt, string $failure, float $delay): void
    {
        $this->logger->warning(sprintf(
            '%s request to "%s" failed with %s, retrying (attempt %d of %d)',
            $request->getMethod(),
            (string) $request->getUri(),
            $failure,
            $attempt + 1,
            $this->maxAttempts,
        ));

        $this->clock->sleep($delay);
    }

    private function rewindBody(RequestInterface $request): void
    {
        $body = $request->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }
    }
}
