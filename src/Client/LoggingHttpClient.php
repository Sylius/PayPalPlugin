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

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;

final readonly class LoggingHttpClient implements ClientInterface
{
    private const MASK = '***';

    private const SENSITIVE_HEADERS = ['authorization', 'paypal-auth-assertion'];

    private const SENSITIVE_KEYS = [
        'access_token', 'refresh_token', 'id_token', 'client_token', 'client_id', 'client_secret', 'code', 'code_verifier', 'nonce', 'payer_id',
        'given_name', 'surname', 'full_name', 'email_address', 'phone', 'phone_number', 'national_number', 'birth_date', 'tax_info', 'address',
        'number', 'security_code', 'expiry',
    ];

    private const CARD_KEY = 'card';

    public function __construct(
        private ClientInterface $client,
        private LoggerInterface $logger,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            $this->logger->error(
                sprintf('PayPal API request %s %s failed: %s', $request->getMethod(), (string) $request->getUri(), $exception->getMessage()),
                ['request' => $this->describe($request)],
            );

            throw $exception;
        }

        $statusCode = $response->getStatusCode();
        $successful = $statusCode >= Response::HTTP_OK && $statusCode < Response::HTTP_MULTIPLE_CHOICES;

        $this->logger->log(
            $successful ? LogLevel::INFO : LogLevel::ERROR,
            sprintf(
                'PayPal API request %s %s answered with HTTP %d (PayPal-Debug-Id: %s)',
                $request->getMethod(),
                (string) $request->getUri(),
                $statusCode,
                $response->getHeaderLine('PayPal-Debug-Id'),
            ),
            ['request' => $this->describe($request), 'response' => $this->describe($response)],
        );

        return $response;
    }

    /** @return array{headers: array<string, string>, body: mixed} */
    private function describe(MessageInterface $message): array
    {
        $headers = [];
        foreach ($message->getHeaders() as $name => $values) {
            $headers[$name] = in_array(strtolower($name), self::SENSITIVE_HEADERS, true) ? self::MASK : implode(', ', $values);
        }

        return ['headers' => $headers, 'body' => $this->maskedBody($message)];
    }

    private function maskedBody(MessageInterface $message): mixed
    {
        $stream = $message->getBody();
        $body = (string) $stream;
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        if ('' === $body) {
            return '';
        }

        $content = json_decode($body, true);
        if (is_array($content)) {
            return $this->mask($content);
        }

        if (str_contains($message->getHeaderLine('Content-Type'), 'application/x-www-form-urlencoded')) {
            parse_str($body, $content);

            return $this->mask($content);
        }

        return $body;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function mask(array $data, bool $card = false): array
    {
        foreach ($data as $key => $value) {
            $name = strtolower((string) $key);

            if (in_array($name, self::SENSITIVE_KEYS, true) || ($card && 'name' === $name)) {
                $data[$key] = self::MASK;
            } elseif (is_array($value)) {
                $data[$key] = $this->mask($value, self::CARD_KEY === $name);
            }
        }

        return $data;
    }
}
