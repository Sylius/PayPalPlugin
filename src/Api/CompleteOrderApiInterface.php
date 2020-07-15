<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

interface CompleteOrderApiInterface
{
    public function complete(string $token, string $orderId): array;
}
