<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

interface CountriesProviderInterface
{
    public function provide(): array;
}
