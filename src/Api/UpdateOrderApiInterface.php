<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

interface UpdateOrderApiInterface
{
    public function update(string $token, string $orderId, string $newTotal, string $newCurrencyCode): void;
}
