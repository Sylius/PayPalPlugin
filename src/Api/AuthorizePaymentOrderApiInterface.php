<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

interface AuthorizePaymentOrderApiInterface
{
    public function get(string $token, string $orderId): array;
}
