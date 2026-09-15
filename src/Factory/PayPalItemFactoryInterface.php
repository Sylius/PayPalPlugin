<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Factory;

use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\PayPalPlugin\Model\PayPalItem;

interface PayPalItemFactoryInterface
{
    public function create(
        OrderItemInterface $orderItem,
        int $quantity,
        int $unitPrice,
        int $tax,
        string $currencyCode,
        string $category,
    ): PayPalItem;
}
