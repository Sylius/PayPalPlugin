<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Service;

use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;

final class DummyAuthorizeClientApi implements AuthorizeClientApiInterface
{
    public function authorize(string $clientId, string $clientSecret): string
    {
        return 'TOKEN';
    }
}
