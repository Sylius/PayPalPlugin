<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\OrderItemInterface;

interface OrderItemNonNeutralTaxesProviderInterface
{
    public function provide(OrderItemInterface $orderItem): array;
}
