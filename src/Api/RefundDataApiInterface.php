<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

interface RefundDataApiInterface
{
    public function get(string $token, string $refundId): array;
}
