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
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Symfony\Component\HttpFoundation\Request;

final readonly class OnboardingTokenApi implements OnboardingTokenApiInterface
{
    public function __construct(
        private ClientInterface $client,
        private string $baseUrl,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     * @throws PayPalPluginException
     */
    public function getFromAuthorizationCode(string $sharedId, string $authCode, string $sellerNonce): string
    {
        $request = $this->requestFactory->createRequest(Request::METHOD_POST, $this->baseUrl . 'v1/oauth2/token')
            ->withHeader('Authorization', 'Basic ' . base64_encode($sharedId . ':'))
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
        ;

        $request = $request->withBody(
            $this->streamFactory->createStream(
                http_build_query(
                    data: [
                        'grant_type' => 'authorization_code',
                        'code' => $authCode,
                        'code_verifier' => $sellerNonce,
                    ],
                    arg_separator: '&',
                ),
            ),
        );

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->logger->error(sprintf('Error while receiving access_token %d: %s', $e->getCode(), $e->getMessage()));

            throw $e;
        }

        $content = (array) json_decode(
            json: $response->getBody()->getContents(),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        if (!isset($content['access_token'])) {
            $this->logger->error('Missing access_token', $content);

            throw new PayPalPluginException('Missing access_token');
        }

        return (string) $content['access_token'];
    }
}
