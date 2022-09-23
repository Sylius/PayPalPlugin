<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

interface PayPalRefundDataProviderInterface
{
    public function provide(string $refundRefundUrl): array;
}
