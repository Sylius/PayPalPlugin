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

namespace Sylius\PayPalPlugin\Client;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiTimeoutException;
use Sylius\PayPalPlugin\Exception\PayPalAuthorizationException;
use Sylius\PayPalPlugin\Provider\HostProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class PayPalClient implements PayPalClientInterface
{
    private const RETRYABLE_STATUS_CODES = [
        Response::HTTP_TOO_MANY_REQUESTS,
        Response::HTTP_INTERNAL_SERVER_ERROR,
        Response::HTTP_BAD_GATEWAY,
        Response::HTTP_SERVICE_UNAVAILABLE,
        Response::HTTP_GATEWAY_TIMEOUT,
    ];

    private const INITIAL_RETRY_DELAY_IN_MILLISECONDS = 250;

    private const MAX_RETRY_DELAY_IN_MILLISECONDS = 2000;

    public function __construct(
        private ClientInterface $client,
        private LoggerInterface $logger,
        private UuidProviderInterface $uuidProvider,
        private PayPalConfigurationProviderInterface $payPalConfigurationProvider,
        private ChannelContextInterface $channelContext,
        private HostProviderInterface $hostProvider,
        private int $maxAttempts,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private ClockInterface $clock,
        private bool $loggingLevelIncreased = false,
    ) {
    }

    public function authorize(string $clientId, string $clientSecret, bool $sandbox): array
    {
        $response = $this->doRequest(
            'POST',
            $this->hostProvider->getApiBaseUrlForMode($sandbox) . 'v1/oauth2/token',
            [
                'auth' => [$clientId, $clientSecret],
                'form_params' => ['grant_type' => 'client_credentials'],
            ],
        );

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            throw new PayPalAuthorizationException();
        }

        return (array) json_decode($response->getBody()->getContents(), true);
    }

    public function get(string $url, string $token): array
    {
        return $this->request('GET', $url, $token);
    }

    public function post(string $url, string $token, ?array $data = null, array $extraHeaders = []): array
    {
        $headers = array_merge(['PayPal-Request-Id' => $this->uuidProvider->provide()], $extraHeaders);

        return $this->request('POST', $url, $token, $data, $headers);
    }

    public function patch(string $url, string $token, ?array $data = null): array
    {
        return $this->request('PATCH', $url, $token, $data);
    }

    private function request(string $method, string $url, string $token, ?array $data = null, array $extraHeaders = []): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $partnerAttributionId = $this->partnerAttributionId();
        if (null !== $partnerAttributionId) {
            $headers['PayPal-Partner-Attribution-Id'] = $partnerAttributionId;
        }

        $options = ['headers' => array_merge($headers, $extraHeaders)];

        if ($data !== null) {
            $options['json'] = $data;
        }

        $fullUrl = $this->hostProvider->getApiBaseUrl() . $url;

        $response = $this->doRequest($method, $fullUrl, $options);
        if ($this->loggingLevelIncreased) {
            $this->logger->debug(sprintf('%s request to "%s" called successfully', $method, $fullUrl));
        }

        $content = (array) json_decode($response->getBody()->getContents(), true);

        if (
            (!in_array($response->getStatusCode(), [Response::HTTP_OK, Response::HTTP_NO_CONTENT], true)) &&
            isset($content['debug_id'])
        ) {
            $this
                ->logger
                ->error(sprintf('%s request to "%s" failed with debug ID %s', $method, $fullUrl, (string) $content['debug_id']))
            ;
        }

        return $content;
    }

    private function partnerAttributionId(): ?string
    {
        try {
            /** @var ChannelInterface $channel */
            $channel = $this->channelContext->getChannel();

            return $this->payPalConfigurationProvider->getPartnerAttributionId($channel);
        } catch (\Throwable $exception) {
            $this->logger->warning(sprintf(
                'Could not resolve the PayPal partner attribution id: %s',
                $exception->getMessage(),
            ));

            return null;
        }
    }

    private function doRequest(string $method, string $fullUrl, array $options): ResponseInterface
    {
        $request = $this->requestFactory->createRequest($method, $fullUrl);

        if (isset($options['auth'])) {
            $request = $request->withHeader(
                'Authorization',
                sprintf(
                    'Basic %s',
                    base64_encode(sprintf('%s:%s', $options['auth'][0], $options['auth'][1])),
                ),
            );
        }

        if (isset($options['form_params'])) {
            $request = $request->withHeader('Content-Type', 'application/x-www-form-urlencoded');
            $request = $request->withBody(
                $this->streamFactory->createStream(
                    http_build_query(
                        $options['form_params'],
                        '',
                        '&',
                        \PHP_QUERY_RFC1738,
                    ),
                ),
            );
        }

        if (isset($options['json'])) {
            $request = $request->withBody(
                $this->streamFactory->createStream(json_encode($options['json'])),
            );
        }

        if (isset($options['headers'])) {
            foreach ($options['headers'] as $header => $headerValue) {
                $request = $request->withHeader($header, $headerValue);
            }
        }

        return $this->send($request);
    }

    private function send(RequestInterface $request): ResponseInterface
    {
        for ($attempt = 1; ; ++$attempt) {
            $this->rewindBody($request);

            try {
                $response = $this->client->sendRequest($request);
            } catch (NetworkExceptionInterface $exception) {
                if ($attempt >= $this->maxAttempts) {
                    throw new PayPalApiTimeoutException();
                }

                $this->retryAfter($request, $attempt, $exception->getMessage(), $this->backoffDelay($attempt));

                continue;
            }

            if ($attempt >= $this->maxAttempts || !in_array($response->getStatusCode(), self::RETRYABLE_STATUS_CODES, true)) {
                return $response;
            }

            $delay = $this->retryDelay($response, $attempt);
            if (null === $delay) {
                return $response;
            }

            $this->retryAfter($request, $attempt, sprintf('HTTP %d', $response->getStatusCode()), $delay);
        }
    }

    private function retryDelay(ResponseInterface $response, int $attempt): ?float
    {
        $retryAfter = $response->getHeaderLine('Retry-After');
        if (!ctype_digit($retryAfter)) {
            return $this->backoffDelay($attempt);
        }

        $delay = (int) $retryAfter * 1000;

        return $delay > self::MAX_RETRY_DELAY_IN_MILLISECONDS ? null : $delay / 1000;
    }

    private function backoffDelay(int $attempt): float
    {
        $maxDelay = min(self::MAX_RETRY_DELAY_IN_MILLISECONDS, self::INITIAL_RETRY_DELAY_IN_MILLISECONDS * 2 ** ($attempt - 1));

        return random_int(0, $maxDelay) / 1000;
    }

    private function retryAfter(RequestInterface $request, int $attempt, string $failure, float $delay): void
    {
        $this->logger->warning(sprintf(
            '%s request to "%s" failed with %s, retrying (attempt %d of %d)',
            $request->getMethod(),
            (string) $request->getUri(),
            $failure,
            $attempt + 1,
            $this->maxAttempts,
        ));

        $this->clock->sleep($delay);
    }

    private function rewindBody(RequestInterface $request): void
    {
        $body = $request->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }
    }
}
