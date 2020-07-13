<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\OrderInterface;

interface OrderProviderInterface
{
    public function provideOrderById(int $id): OrderInterface;

    public function provideOrderByToken(string $token): OrderInterface;
}
