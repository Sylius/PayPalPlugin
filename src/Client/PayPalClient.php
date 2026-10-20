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
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Provider\HostProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class PayPalClient implements PayPalClientInterface
{
    public function __construct(
        private ClientInterface $client,
        private LoggerInterface $logger,
        private UuidProviderInterface $uuidProvider,
        private PayPalConfigurationProviderInterface $payPalConfigurationProvider,
        private ChannelContextInterface $channelContext,
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private HostProviderInterface $hostProvider,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private bool $loggingLevelIncreased = false,
    ) {
    }

    public function get(PaymentMethodInterface $paymentMethod, string $url): array
    {
        return $this->request($paymentMethod, 'GET', $url);
    }

    public function post(PaymentMethodInterface $paymentMethod, string $url, ?array $data = null, array $extraHeaders = []): array
    {
        $headers = array_merge(['PayPal-Request-Id' => $this->uuidProvider->provide()], $extraHeaders);

        return $this->request($paymentMethod, 'POST', $url, $data, $headers);
    }

    public function patch(PaymentMethodInterface $paymentMethod, string $url, ?array $data = null): array
    {
        return $this->request($paymentMethod, 'PATCH', $url, $data);
    }

    public function delete(PaymentMethodInterface $paymentMethod, string $url): array
    {
        return $this->request($paymentMethod, 'DELETE', $url);
    }

    private function request(PaymentMethodInterface $paymentMethod, string $method, string $url, ?array $data = null, array $extraHeaders = []): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $partnerAttributionId = $this->partnerAttributionId($paymentMethod);
        if (null !== $partnerAttributionId) {
            $headers['PayPal-Partner-Attribution-Id'] = $partnerAttributionId;
        }

        $options = ['headers' => array_merge($headers, $extraHeaders)];

        if ($data !== null) {
            $options['json'] = $data;
        }

        $fullUrl = $this->apiBaseUrl($paymentMethod) . $url;

        $response = $this->doRequest($method, $url, $fullUrl, $options, $this->authorizeClientApi->authorize($paymentMethod));
        if (Response::HTTP_UNAUTHORIZED === $response->getStatusCode()) {
            $this->authorizeClientApi->invalidate($paymentMethod);
            $response = $this->doRequest($method, $url, $fullUrl, $options, $this->authorizeClientApi->authorize($paymentMethod));
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode < Response::HTTP_OK || $statusCode >= Response::HTTP_MULTIPLE_CHOICES) {
            throw PayPalApiException::fromResponse($method, $url, $response);
        }

        if ($this->loggingLevelIncreased) {
            $this->logger->debug(sprintf('%s request to "%s" called successfully', $method, $fullUrl));
        }

        return (array) json_decode((string) $response->getBody(), true);
    }

    private function apiBaseUrl(PaymentMethodInterface $paymentMethod): string
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return $this->hostProvider->getApiBaseUrlForMode(PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->isSandbox());
    }

    private function partnerAttributionId(PaymentMethodInterface $paymentMethod): ?string
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if ($gatewayConfig instanceof GatewayConfigInterface) {
            $payPalGatewayConfig = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);
            if ($payPalGatewayConfig->hasPartnerAttributionId()) {
                return $payPalGatewayConfig->partnerAttributionId();
            }
        }

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

    private function doRequest(string $method, string $url, string $fullUrl, array $options, string $token): ResponseInterface
    {
        $request = $this->requestFactory->createRequest($method, $fullUrl)->withHeader('Authorization', 'Bearer ' . $token);

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

        try {
            return $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw PayPalApiException::fromClientFailure($method, $url, $exception);
        }
    }
}
