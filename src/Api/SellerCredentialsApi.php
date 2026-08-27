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
use Symfony\Component\HttpFoundation\Request;

final readonly class SellerCredentialsApi implements SellerCredentialsApiInterface
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
     * @throws PayPalPluginException
     * @throws JsonException
     */
    public function get(string $onboardingToken, string $partnerId): array
    {
        $request = $this->requestFactory->createRequest(
            Request::METHOD_GET,
            sprintf('%sv1/customer/partners/%s/merchant-integrations/credentials', $this->baseUrl, $partnerId),
        )
            ->withHeader('Authorization', 'Bearer ' . $onboardingToken)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
        ;

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->logger->error(sprintf('Error while receiving client data %d: %s', $e->getCode(), $e->getMessage()));

            throw $e;
        }

        $content = (array) json_decode(
            json: $response->getBody()->getContents(),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        if (!isset($content['client_id'], $content['client_secret'], $content['payer_id'])) {
            $this->logger->error('client_id/client_secret/payer_id is missing in response', $content);

            throw new PayPalPluginException('client_id/client_secret/payer_id is missing in response');
        }

        return [
            'client_id' => (string) $content['client_id'],
            'client_secret' => (string) $content['client_secret'],
            'payer_id' => (string) $content['payer_id'],
        ];
    }
}
