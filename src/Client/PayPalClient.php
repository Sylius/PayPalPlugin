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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiTimeoutException;
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

    private function request(PaymentMethodInterface $paymentMethod, string $method, string $url, ?array $data = null, array $extraHeaders = []): array
    {
        $headers = [
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

        $fullUrl = $this->apiBaseUrl($paymentMethod) . $url;

        $response = $this->doRequest($method, $fullUrl, $options, $this->authorizeClientApi->authorize($paymentMethod));
        if (Response::HTTP_UNAUTHORIZED === $response->getStatusCode()) {
            $this->authorizeClientApi->invalidate($paymentMethod);
            $response = $this->doRequest($method, $fullUrl, $options, $this->authorizeClientApi->authorize($paymentMethod));
        }

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

    private function apiBaseUrl(PaymentMethodInterface $paymentMethod): string
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return $this->hostProvider->getApiBaseUrlForMode(PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->isSandbox());
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

    private function doRequest(string $method, string $fullUrl, array $options, string $token): ResponseInterface
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
        } catch (NetworkExceptionInterface) {
            throw new PayPalApiTimeoutException();
        }
    }
}
