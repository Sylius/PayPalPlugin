<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

interface UpdateOrderApiInterface
{
    public function update(
        string $token,
        string $orderId,
        string $referenceId,
        string $newTotal,
        string $newItemsTotal,
        string $newShippingTotal,
        string $newTaxTotal,
        string $newCurrencyCode
    ): void;
}
