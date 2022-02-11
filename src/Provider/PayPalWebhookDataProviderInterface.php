<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

interface PayPalWebhookDataProviderInterface
{
    public function provide(string $paypalUrl, string $rel): array;
}
