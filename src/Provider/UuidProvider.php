<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Ramsey\Uuid\Uuid;

final class UuidProvider implements UuidProviderInterface
{
    public function provide(): string
    {
        return Uuid::uuid4()->toString();
    }
}
