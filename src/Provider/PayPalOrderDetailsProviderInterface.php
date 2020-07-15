<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\PaymentInterface;

interface PayPalOrderDetailsProviderInterface
{
    public function provide(string $clientId, string $clientSecret, string $orderId): array;
}
