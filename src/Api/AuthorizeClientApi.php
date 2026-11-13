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

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiAuthenticationException;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Model\AccessToken;
use Sylius\PayPalPlugin\Provider\HostProviderInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthorizeClientApi implements AuthorizeClientApiInterface
{
    private const PATH = 'v1/oauth2/token';

    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private HostProviderInterface $hostProvider,
    ) {
    }

    public function authorize(string $clientId, string $clientSecret, bool $sandbox): AccessToken
    {
        $request = $this->requestFactory->createRequest('POST', $this->hostProvider->getApiBaseUrlForMode($sandbox) . self::PATH)
            ->withHeader('Authorization', 'Basic ' . base64_encode($clientId . ':' . $clientSecret))
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streamFactory->createStream(http_build_query(['grant_type' => 'client_credentials'])));

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw PayPalApiException::fromClientFailure('POST', self::PATH, $exception);
        }

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw PayPalApiException::fromResponse('POST', self::PATH, $response);
        }

        $content = (array) json_decode((string) $response->getBody(), true);

        if (!is_string($content['access_token'] ?? null) || !is_numeric($content['expires_in'] ?? null)) {
            throw new PayPalApiAuthenticationException('POST', self::PATH, Response::HTTP_OK, errorMessage: 'PayPal answered without an access token or its lifetime.');
        }

        return new AccessToken($content['access_token'], (int) $content['expires_in']);
    }
}
