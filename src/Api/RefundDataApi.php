<?php

declare(strict_types=1);


namespace Sylius\PayPalPlugin\Api;

use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class RefundDataApi implements RefundDataApiInterface
{
    /** @var PayPalClientInterface */
    private $client;

    public function __construct(PayPalClientInterface $client) {
        $this->client = $client;
    }

    public function get(string $token, string $refundId): array
    {
        return $this->client->get(sprintf('v1/payments/refund/%s', $refundId), $token);
    }
}
