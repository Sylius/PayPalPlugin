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
use Sylius\PayPalPlugin\Exception\PayPalOrderUpdateException;

final class UpdateOrderApi implements UpdateOrderApiInterface
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

    public function update(string $token, string $orderId, string $newTotal, string $newCurrencyCode): void
    {
        $response = $this->client->request(
            'PATCH',
            sprintf('%sv2/checkout/orders/%s', $this->baseUrl, $orderId),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'PayPal-Partner-Attribution-Id' => $this->partnerAttributionId,
                ],
                'json' => [
                    [
                        'op' => 'replace',
                        'path' => '/purchase_units/@reference_id==\'default\'/amount',
                        'value' => ['value' => $newTotal, 'currency_code' => $newCurrencyCode],
                    ]
                ],
            ]
        );

        if ($response->getStatusCode() !== 204) {
            throw new PayPalOrderUpdateException();
        }
    }
}
