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

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\OrderItemInterface;

interface OrderItemTaxesProviderInterface
{
    /**
     * @return array{
     *     total: int,
     *     itemTaxes: array<string, array{0: int, 1: int}>
     * }
     */
    public function provide(OrderItemInterface $orderItem): array;
}
