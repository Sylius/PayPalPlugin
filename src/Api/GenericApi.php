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
use Psr\Http\Message\RequestFactoryInterface;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Symfony\Component\HttpFoundation\Response;

final readonly class GenericApi implements GenericApiInterface
{
    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
    ) {
    }

    public function get(string $token, string $url): array
    {
        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json');

        $response = $this->client->sendRequest($request);

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new PayPalPluginException();
        }

        return (array) json_decode($response->getBody()->getContents(), true);
    }
}
