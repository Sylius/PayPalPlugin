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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class CreateOrderApi implements CreateOrderApiInterface
{
    /** @var Client */
    private $client;

    /** @var string */
    private $baseUrl;

    public function __construct(Client $client, string $baseUrl)
    {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
    }

    public function create(string $token, PaymentInterface $payment): array
    {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => $order->getCurrencyCode(),
                        'value' => (int) $payment->getAmount() / 100,
                    ],
                    'payee' => [
                        // TODO: change hardcoded seller data
                        'email_address' => 'sb-ecyrw2404052@business.example.com',
                        'merchant_id' => 'L7WWW2B328J7J',
                    ],
                ],
            ],
        ];

        $response = $this->client->request(
            'POST',
            $this->baseUrl . 'v2/checkout/orders',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'PayPal-Partner-Attribution-Id' => 'sylius-ppcp4p-bn-code',
                ],
                'json' => $data,
            ]
        );

        return (array) json_decode($response->getBody()->getContents(), true);
    }
}
