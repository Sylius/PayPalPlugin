<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Client;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class PayPalClient implements PayPalClientInterface
{
    /** @var ClientInterface */
    private $client;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $trackingId;

    public function __construct(ClientInterface $client, LoggerInterface $logger, string $baseUrl, string $trackingId)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->baseUrl = $baseUrl;
        $this->trackingId = $trackingId;
    }

    public function get(string $url, string $token): array
    {
        return $this->request('GET', $url, $token);
    }

    public function post(string $url, string $token, array $data = null): array
    {
        return $this->request('POST', $url, $token, $data);
    }

    public function patch(string $url, string $token, array $data = null): array
    {
        return $this->request('PATCH', $url, $token, $data);
    }

    private function request(string $method, string $url, string $token, array $data = null): array
    {
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'PayPal-Partner-Attribution-Id' => $this->trackingId,
            ],
        ];

        if ($data !== null) {
            $options['json'] = $data;
        }

        $fullUrl = $this->baseUrl . $url;

        try {
            /** @var ResponseInterface $response */
            $response = $this->client->request($method, $fullUrl, $options);
        } catch (RequestException $exception) {
            /** @var ResponseInterface $response */
            $response = $exception->getResponse();
        }

        $content = (array) json_decode($response->getBody()->getContents(), true);

        if (
            (!in_array($response->getStatusCode(), [200, 204])) &&
            isset($content['debug_id'])
        ) {
            $this
                ->logger
                ->error(sprintf('%s request to "%s" failed with debug ID %s', $method, $fullUrl, (string) $content['debug_id']))
            ;
        }

        return $content;
    }
}
