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

namespace Sylius\PayPalPlugin\Api;

use GuzzleHttp\Client;

final class CompleteOrderApi implements CompleteOrderApiInterface
{
    /** @var Client */
    private $client;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $partnerAttributionId;

    public function __construct(Client $client, string $baseUrl, string $partnerAttributionId)
    {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
        $this->partnerAttributionId = $partnerAttributionId;
    }

    public function complete(string $token, string $orderId): array
    {
        $response = $this->client->request(
            'POST',
            sprintf('%sv2/checkout/orders/%s/capture', $this->baseUrl, $orderId),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Prefer' => 'return=representation',
                    'PayPal-Partner-Attribution-Id' => $this->partnerAttributionId,
                    'Content-Type' => 'application/json',
                ],
            ]
        );

        return (array) json_decode($response->getBody()->getContents(), true);
    }
}
