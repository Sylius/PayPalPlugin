<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

final class GenericApi implements GenericApiInterface
{
    public function __construct(
        private readonly GuzzleClientInterface|ClientInterface $client,
        private readonly ?RequestFactoryInterface $requestFactory = null,
    ) {
        if ($this->client instanceof GuzzleClientInterface) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                'Passing GuzzleHttp\ClientInterface as a first argument in the constructor is deprecated and will be removed. Use Psr\Http\Client\ClientInterface instead.',
            );
        }

        if (null === $this->requestFactory) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                'Not passing $requestFactory to %s constructor is deprecated and will be removed',
                self::class,
            );
        }
    }

    public function get(string $token, string $url): array
    {
        if ($this->client instanceof GuzzleClientInterface && null === $this->requestFactory) {
            return $this->legacyGet($token, $url);
        }

        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json');

        return (array) json_decode($this->client->sendRequest($request)->getBody()->getContents(), true);
    }

    private function legacyGet(string $token, string $url): array
    {
        $response = $this->client->request('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);

        return (array) json_decode($response->getBody()->getContents(), true);
    }
}
