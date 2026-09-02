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

namespace Sylius\PayPalPlugin\Api;

use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Symfony\Component\HttpFoundation\Response;

final readonly class PayPalOnboardingRequestExecutor implements PayPalOnboardingRequestExecutorInterface
{
    public function __construct(
        private ClientInterface $client,
        private LoggerInterface $logger,
    ) {
    }

    public function execute(RequestInterface $request, string $operation): array
    {
        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            $this->logger->error(sprintf('%s request failed: %s', $operation, $exception->getMessage()));

            throw $exception;
        }

        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        if ($statusCode < Response::HTTP_OK || $statusCode >= Response::HTTP_MULTIPLE_CHOICES) {
            $this->logger->error(sprintf('%s request failed with HTTP %d: %s', $operation, $statusCode, $this->sanitizeBody($body)));

            throw new PayPalPluginException(sprintf('%s request failed with HTTP %d', $operation, $statusCode));
        }

        return (array) json_decode($body, associative: true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @throws JsonException
     */
    private function sanitizeBody(string $body): string
    {
        $decoded = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);

        if (is_array($decoded)) {
            $sensitiveKeys = ['access_token', 'refresh_token', 'client_secret', 'client_id', 'payer_id', 'nonce'];
            foreach ($sensitiveKeys as $key) {
                if (array_key_exists($key, $decoded)) {
                    $decoded[$key] = '[redacted]';
                }
            }

            $body = (string) json_encode($decoded, \JSON_THROW_ON_ERROR);
        }

        return mb_strlen($body) > 1000 ? mb_substr($body, 0, 1000) . '...' : $body;
    }
}
