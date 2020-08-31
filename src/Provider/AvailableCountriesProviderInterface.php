<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

interface AvailableCountriesProviderInterface
{
    /** @return string[] */
    public function provide(): array;
}
