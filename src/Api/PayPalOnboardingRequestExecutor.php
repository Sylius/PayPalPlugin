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

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Symfony\Component\HttpFoundation\Response;

final readonly class PayPalOnboardingRequestExecutor implements PayPalOnboardingRequestExecutorInterface
{
    public function __construct(
        private ClientInterface $client,
        private string $partnerAttributionId,
    ) {
    }

    public function execute(RequestInterface $request, string $operation): array
    {
        $request = $request->withHeader('PayPal-Partner-Attribution-Id', $this->partnerAttributionId);

        $response = $this->client->sendRequest($request);

        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();
        $debugId = $response->getHeaderLine('PayPal-Debug-Id');

        if ($statusCode < Response::HTTP_OK || $statusCode >= Response::HTTP_MULTIPLE_CHOICES) {
            throw new PayPalPluginException(sprintf('%s request failed with HTTP %d (PayPal-Debug-Id: %s)', $operation, $statusCode, $debugId));
        }

        return (array) json_decode($body, associative: true, flags: \JSON_THROW_ON_ERROR);
    }
}
