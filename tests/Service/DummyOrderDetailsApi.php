<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Service;

use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;

final class DummyOrderDetailsApi implements OrderDetailsApiInterface
{
    public function get(string $token, string $orderId): array
    {
        return ['status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [['id' => '123123']]]]]];
    }
}
