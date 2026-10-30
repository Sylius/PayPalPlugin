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

namespace Tests\Sylius\PayPalPlugin\Behat\Mocker;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class PayPalHttpClientWithExpectations implements ClientInterface
{
    public const CACHE_KEY = 'paypal_http_client_expectations';

    public const SENT_REQUESTS_CACHE_KEY = 'paypal_http_client_sent_requests';

    private const CONNECTION_FAILURE = 0;

    public function __construct(
        private CacheItemPoolInterface $cache,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function addExpectation(string $method, string $path, array $body = [], int $statusCode = 200, array $headers = []): void
    {
        $expectations = $this->getExpectations();
        $expectations[] = [
            'method' => strtoupper($method),
            'path' => '/' . ltrim($path, '/'),
            'body' => $body,
            'statusCode' => $statusCode,
            'headers' => $headers,
        ];

        $this->saveExpectations($expectations);
    }

    public function addConnectionFailure(string $method, string $path): void
    {
        $this->addExpectation($method, $path, [], self::CONNECTION_FAILURE);
    }

    public function resetExpectations(): void
    {
        $this->cache->deleteItems([self::CACHE_KEY, self::SENT_REQUESTS_CACHE_KEY]);
    }

    public function hasExpectations(): bool
    {
        return [] !== $this->getExpectations();
    }

    /** @return list<array{method: string, path: string, body: array<string, mixed>, statusCode: int, headers: array<string, string>}> */
    public function getExpectations(): array
    {
        /** @var list<array{method: string, path: string, body: array<string, mixed>, statusCode: int, headers: array<string, string>}> $expectations */
        $expectations = $this->getCacheItem()->get() ?? [];

        return $expectations;
    }

    /** @return list<array{method: string, path: string, body: mixed}> */
    public function getSentRequests(): array
    {
        /** @var list<array{method: string, path: string, body: mixed}> $sentRequests */
        $sentRequests = $this->cache->getItem(self::SENT_REQUESTS_CACHE_KEY)->get() ?? [];

        return $sentRequests;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $currentRequest = $request->getMethod() . ' ' . $request->getUri()->getPath();
        $this->saveSentRequest($request);

        $expectations = $this->getExpectations();
        $expectation = array_shift($expectations);

        if (null === $expectation) {
            throw new \RuntimeException(sprintf('No expectations found for the PayPal test request "%s".', $currentRequest));
        }

        $expectedRequest = $expectation['method'] . ' ' . $expectation['path'];
        if ($expectedRequest !== $currentRequest) {
            throw new \RuntimeException(sprintf('Expected the PayPal request "%s" but got "%s".', $expectedRequest, $currentRequest));
        }

        foreach ($expectation['headers'] as $header => $value) {
            if ($value !== $request->getHeaderLine($header)) {
                throw new \RuntimeException(sprintf(
                    'Expected the PayPal request "%s" with the %s header "%s" but got "%s".',
                    $currentRequest,
                    $header,
                    $value,
                    $request->getHeaderLine($header),
                ));
            }
        }

        $this->saveExpectations($expectations);

        if (self::CONNECTION_FAILURE === $expectation['statusCode']) {
            throw new PayPalNetworkFailure(sprintf('Could not connect for the PayPal test request "%s".', $currentRequest), $request);
        }

        return $this->responseFactory
            ->createResponse($expectation['statusCode'])
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode($expectation['body'], \JSON_THROW_ON_ERROR)))
        ;
    }

    /** @param list<array{method: string, path: string, body: array<string, mixed>, statusCode: int, headers: array<string, string>}> $expectations */
    private function saveExpectations(array $expectations): void
    {
        $cacheItem = $this->getCacheItem();
        $cacheItem->set($expectations);
        $this->cache->save($cacheItem);
    }

    private function saveSentRequest(RequestInterface $request): void
    {
        $sentRequests = $this->getSentRequests();
        $sentRequests[] = [
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'body' => json_decode((string) $request->getBody(), true),
        ];

        $cacheItem = $this->cache->getItem(self::SENT_REQUESTS_CACHE_KEY);
        $cacheItem->set($sentRequests);
        $this->cache->save($cacheItem);
    }

    private function getCacheItem(): CacheItemInterface
    {
        return $this->cache->getItem(self::CACHE_KEY);
    }
}
