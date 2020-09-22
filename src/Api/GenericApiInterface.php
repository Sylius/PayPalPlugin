<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

interface GenericApiInterface
{
    public function get(string $token, string $url): array;
}
