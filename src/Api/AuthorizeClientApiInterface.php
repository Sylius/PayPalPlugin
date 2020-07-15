<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

interface AuthorizeClientApiInterface
{
    public function authorize(string $clientId, string $clientSecret): string;
}
