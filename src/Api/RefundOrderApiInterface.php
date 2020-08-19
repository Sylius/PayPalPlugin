<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

interface RefundOrderApiInterface
{
    public function refund(string $token, string $orderId): array;
}
