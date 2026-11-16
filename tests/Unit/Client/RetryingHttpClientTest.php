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

namespace Tests\Sylius\PayPalPlugin\Unit\Client;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Sylius\PayPalPlugin\Client\RetryingHttpClient;
use Symfony\Component\Clock\MockClock;
use Tests\Sylius\PayPalPlugin\Behat\Mocker\PayPalNetworkFailure;

final class RetryingHttpClientTest extends TestCase
{
    private Psr17Factory $factory;

    private MockClock $clock;

    /** @var list<int|string|array{int, array<string, string>}> */
    private array $responses = [];

    /** @var list<RequestInterface> */
    private array $sentRequests = [];

    /** @var list<string> */
    private array $sentBodies = [];

    private RetryingHttpClient $retryingHttpClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new Psr17Factory();
        $this->clock = new MockClock();

        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturnCallback(function (RequestInterface $request): ResponseInterface {
            $this->sentRequests[] = $request;
            $this->sentBodies[] = $request->getBody()->getContents();

            $response = array_shift($this->responses) ?? throw new \LogicException('No more responses to give.');
            if ('network' === $response) {
                throw new PayPalNetworkFailure('Connection timeout', $request);
            }

            [$statusCode, $headers] = is_array($response) ? $response : [$response, []];
            $psrResponse = $this->factory->createResponse($statusCode);
            foreach ($headers as $name => $value) {
                $psrResponse = $psrResponse->withHeader($name, $value);
            }

            return $psrResponse;
        });

        $this->retryingHttpClient = new RetryingHttpClient($client, new NullLogger(), $this->clock, 3);
    }

    public function test_it_retries_a_request_paypal_could_not_handle(): void
    {
        $this->responses = [503, 200];

        self::assertSame(200, $this->send()->getStatusCode());
        self::assertCount(2, $this->sentRequests);
    }

    public function test_it_retries_a_request_that_did_not_reach_paypal(): void
    {
        $this->responses = ['network', 200];

        self::assertSame(200, $this->send()->getStatusCode());
        self::assertCount(2, $this->sentRequests);
    }

    public function test_it_retries_a_rate_limited_request_after_the_delay_paypal_asks_for(): void
    {
        $this->responses = [[429, ['Retry-After' => '2']], 200];
        $now = $this->clock->now();

        $this->send();

        self::assertCount(2, $this->sentRequests);
        self::assertEquals($now->modify('+2 seconds'), $this->clock->now());
    }

    public function test_it_does_not_wait_longer_than_it_allows_for_a_rate_limited_request(): void
    {
        $this->responses = [[429, ['Retry-After' => '60']]];

        self::assertSame(429, $this->send()->getStatusCode());
        self::assertCount(1, $this->sentRequests);
    }

    public function test_it_does_not_retry_a_request_paypal_rejected(): void
    {
        $this->responses = [400];

        self::assertSame(400, $this->send()->getStatusCode());
        self::assertCount(1, $this->sentRequests);
    }

    public function test_it_returns_the_last_response_after_the_last_attempt(): void
    {
        $this->responses = [503, 503, 503];

        self::assertSame(503, $this->send()->getStatusCode());
        self::assertCount(3, $this->sentRequests);
    }

    public function test_it_rethrows_the_network_failure_after_the_last_attempt(): void
    {
        $this->responses = ['network', 'network', 'network'];

        try {
            $this->send();
            self::fail('The network failure was not rethrown');
        } catch (PayPalNetworkFailure) {
            self::assertCount(3, $this->sentRequests);
        }
    }

    public function test_it_gives_every_request_its_own_attempts(): void
    {
        $this->responses = ['network', 'network', 200, 'network', 'network', 200];

        $this->send();
        $this->send();

        self::assertCount(6, $this->sentRequests);
    }

    public function test_it_resends_the_same_request_with_its_whole_body(): void
    {
        $this->responses = ['network', 503, 201];
        $request = $this->factory->createRequest('POST', 'https://api.sandbox.paypal.com/v2/checkout/orders/123123/capture')
            ->withHeader('PayPal-Request-Id', 'REQUEST_ID')
            ->withBody($this->factory->createStream('{"note":"capture"}'));

        $this->retryingHttpClient->sendRequest($request);

        self::assertSame(array_fill(0, 3, 'REQUEST_ID'), array_map(static fn (RequestInterface $sent): string => $sent->getHeaderLine('PayPal-Request-Id'), $this->sentRequests));
        self::assertSame(array_fill(0, 3, '{"note":"capture"}'), $this->sentBodies);
    }

    private function send(): ResponseInterface
    {
        return $this->retryingHttpClient->sendRequest($this->factory->createRequest('GET', 'https://api.sandbox.paypal.com/v2/checkout/orders/123123'));
    }
}
