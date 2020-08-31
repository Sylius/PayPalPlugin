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
use Ramsey\Uuid\Uuid;

final class RefundPaymentApi implements RefundPaymentApiInterface
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

    public function refund(string $token, string $paymentId): array
    {
        $response = $this->client->request(
            'POST',
            sprintf('%sv2/payments/captures/%s/refund', $this->baseUrl, $paymentId),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'PayPal-Partner-Attribution-Id' => $this->partnerAttributionId,
                    'Content-Type' => 'application/json',
                    'PayPal-Request-Id' => Uuid::uuid4()->toString(),
                ],
            ]
        );

        return (array) json_decode($response->getBody()->getContents(), true);
    }
}
