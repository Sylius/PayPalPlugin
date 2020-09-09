<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\OrderInterface;

interface PayPalItemDataProviderInterface
{
    public function provide(OrderInterface $order): array;
}
