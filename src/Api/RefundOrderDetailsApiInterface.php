<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

interface RefundOrderDetailsApiInterface
{
    public function get(string $token, string $url): array;
}
