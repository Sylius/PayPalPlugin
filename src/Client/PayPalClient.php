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
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiTimeoutException;
use Sylius\PayPalPlugin\Exception\PayPalAuthorizationException;
use Sylius\PayPalPlugin\Provider\PayPalConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;

final class PayPalClient implements PayPalClientInterface
{
    private ClientInterface $client;

    private LoggerInterface $logger;

    private UuidProviderInterface $uuidProvider;

    private PayPalConfigurationProviderInterface $payPalConfigurationProvider;

    private ChannelContextInterface $channelContext;

    private string $baseUrl;

    private int $requestTrialsLimit;

    private bool $loggingLevelIncreased;

    public function __construct(
        ClientInterface $client,
        LoggerInterface $logger,
        UuidProviderInterface $uuidProvider,
        PayPalConfigurationProviderInterface $payPalConfigurationProvider,
        ChannelContextInterface $channelContext,
        string $baseUrl,
        int $requestTrialsLimit,
        bool $loggingLevelIncreased = false
    ) {
        $this->client = $client;
        $this->logger = $logger;
        $this->uuidProvider = $uuidProvider;
        $this->payPalConfigurationProvider = $payPalConfigurationProvider;
        $this->channelContext = $channelContext;
        $this->baseUrl = $baseUrl;
        $this->requestTrialsLimit = $requestTrialsLimit;
        $this->loggingLevelIncreased = $loggingLevelIncreased;
    }

    public function authorize(string $clientId, string $clientSecret): array
    {
        $response = $this->doRequest(
            'POST',
            $this->baseUrl . 'v1/oauth2/token',
            [
                'auth' => [$clientId, $clientSecret],
                'form_params' => ['grant_type' => 'client_credentials'],
            ]
        );

        if ($response->getStatusCode() !== 200) {
            throw new PayPalAuthorizationException();
        }

        return (array) json_decode($response->getBody()->getContents(), true);
    }

    public function get(string $url, string $token): array
    {
        return $this->request('GET', $url, $token);
    }

    public function post(string $url, string $token, array $data = null, array $extraHeaders = []): array
    {
        $headers = array_merge($extraHeaders, ['PayPal-Request-Id' => $this->uuidProvider->provide()]);

        return $this->request('POST', $url, $token, $data, $headers);
    }

    public function patch(string $url, string $token, array $data = null): array
    {
        return $this->request('PATCH', $url, $token, $data);
    }

    private function request(string $method, string $url, string $token, array $data = null, array $extraHeaders = []): array
    {
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();
        $options = [
            'headers' => array_merge([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'PayPal-Partner-Attribution-Id' => $this->payPalConfigurationProvider->getPartnerAttributionId($channel),
            ], $extraHeaders),
        ];

        if ($data !== null) {
            $options['json'] = $data;
        }

        $fullUrl = $this->baseUrl . $url;

        try {
            $response = $this->doRequest($method, $fullUrl, $options);
            if ($this->loggingLevelIncreased) {
                $this->logger->debug(sprintf('%s request to "%s" called successfully', $method, $fullUrl));
            }
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

    private function doRequest(string $method, string $fullUrl, array $options): ResponseInterface
    {
        try {
            $response = $this->client->request($method, $fullUrl, $options);
        } catch (ConnectException $exception) {
            --$this->requestTrialsLimit;
            if ($this->requestTrialsLimit === 0) {
                throw new PayPalApiTimeoutException();
            }

            return $this->doRequest($method, $fullUrl, $options);
        } catch (RequestException $exception) {
            /** @var ResponseInterface $response */
            $response = $exception->getResponse();
        }

        return $response;
    }
}
