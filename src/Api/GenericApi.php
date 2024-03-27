<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;


use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

final class GenericApi implements GenericApiInterface
{

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory
    ){
    }

    public function get(string $token, string $url): array
    {
        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json');

        return (array) json_decode($this->client->sendRequest($request)->getBody()->getContents(), true);
    }
}
