<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Service;

use Sylius\PayPalPlugin\Provider\PayPalRefundDataProviderInterface;

final class DummyPayPalRefundDataProvider implements PayPalRefundDataProviderInterface
{
    public function provide(string $refundId): array
    {
        return ['id' => $refundId];
    }
}
