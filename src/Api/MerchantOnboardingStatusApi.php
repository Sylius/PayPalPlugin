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

namespace Sylius\PayPalPlugin\Api;

use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use const JSON_THROW_ON_ERROR;

final readonly class MerchantOnboardingStatusApi implements MerchantOnboardingStatusApiInterface
{
    public function __construct(
        private ClientInterface $client,
        private string $baseUrl,
        private RequestFactoryInterface $requestFactory,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     * @throws PayPalPluginException
     */
    public function get(string $sellerToken, string $partnerId, string $merchantId): OnboardingStatus
    {
        $request = $this->requestFactory->createRequest(
            Request::METHOD_GET,
            sprintf('%sv1/customer/partners/%s/merchant-integrations/%s', $this->baseUrl, $partnerId, $merchantId),
        )
            ->withHeader('Authorization', 'Bearer ' . $sellerToken)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
        ;

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->logger->error(sprintf('Error while requesting onboarding status %s: %s', $e->getCode(), $e->getMessage()));

            throw $e;
        }

        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        if ($statusCode < Response::HTTP_OK || $statusCode >= Response::HTTP_MULTIPLE_CHOICES) {
            $this->logger->error(sprintf('Onboarding status request failed with HTTP %d: %s', $statusCode, $body));

            throw new PayPalPluginException(sprintf('Onboarding status request failed with HTTP %d', $statusCode));
        }

        $content = (array) json_decode(
            json: $body,
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        return new OnboardingStatus(
            (bool) ($content['payments_receivable'] ?? false),
            (bool) ($content['primary_email_confirmed'] ?? false),
        );
    }
}
