<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class AuthorizePaymentOrderApi implements AuthorizePaymentOrderApiInterface
{
    private PayPalClientInterface $client;

    public function __construct(PayPalClientInterface $client)
    {
        $this->client = $client;
    }

    public function get(string $token, string $orderId): array
    {
        return $this->client->post(sprintf('v2/checkout/orders/%s/authorize', $orderId), $token);
    }
}
