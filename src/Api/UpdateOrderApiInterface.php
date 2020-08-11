<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

use Sylius\PayPalPlugin\Exception\PayPalOrderUpdateException;

interface UpdateOrderApiInterface
{
    /**
     * @throws PayPalOrderUpdateException
     */
    public function update(string $token, string $orderId, string $newTotal, string $newCurrencyCode): void;
}
