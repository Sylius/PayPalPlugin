<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

interface UuidProviderInterface
{
    public function provide(): string;
}
