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
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiTimeoutException;
use Sylius\PayPalPlugin\Exception\PayPalAuthorizationException;
use Sylius\PayPalPlugin\Model\AccessToken;
use Sylius\PayPalPlugin\Provider\HostProviderInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthorizeClientApi implements AuthorizeClientApiInterface
{
    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private HostProviderInterface $hostProvider,
    ) {
    }

    public function authorize(string $clientId, string $clientSecret, bool $sandbox): AccessToken
    {
        $request = $this->requestFactory->createRequest('POST', $this->hostProvider->getApiBaseUrlForMode($sandbox) . 'v1/oauth2/token')
            ->withHeader('Authorization', 'Basic ' . base64_encode($clientId . ':' . $clientSecret))
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streamFactory->createStream(http_build_query(['grant_type' => 'client_credentials'])));

        try {
            $response = $this->client->sendRequest($request);
        } catch (NetworkExceptionInterface) {
            throw new PayPalApiTimeoutException();
        }

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new PayPalAuthorizationException();
        }

        $content = (array) json_decode($response->getBody()->getContents(), true);

        if (!is_string($content['access_token'] ?? null) || !is_numeric($content['expires_in'] ?? null)) {
            throw new PayPalAuthorizationException();
        }

        return new AccessToken($content['access_token'], (int) $content['expires_in']);
    }
}
