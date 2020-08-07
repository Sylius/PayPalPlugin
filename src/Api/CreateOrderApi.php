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
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Webmozart\Assert\Assert;

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

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        $config = $gatewayConfig->getConfig();

        Assert::keyExists($config, 'merchant_id');
        Assert::keyExists($config, 'sylius_merchant_id');

        $data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => $order->getCurrencyCode(),
                        'value' => (int) $payment->getAmount() / 100,
                    ],
                    'payee' => [
                        'merchant_id' => $config['merchant_id'],
                    ],
                    'shipping' => [
                        'name' => ['full_name' => 'John Doe'],
                        'address' => [
                            'address_line_1' => 'Test St. 123',
                            'address_line_2' => '6',
                            'admin_area_1' => 'CA',
                            'admin_area_2' => 'New York',
                            'postal_code' => '32000',
                            'country_code' => 'US',
                        ],
                    ],
                ],
            ],
        ];

        $address = $order->getBillingAddress();
        if ($address !== null) {
            $data['purchase_units'][0]['shipping'] = [
                'name' => ['full_name' => $address->getFullName()],
                'address' => [
                    'address_line_1' => $address->getStreet(),
                    'admin_area_2' => $address->getCity(),
                    'postal_code' => $address->getPostcode(),
                    'country_code' => $address->getCountryCode(),
                ],
            ];
        }

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
