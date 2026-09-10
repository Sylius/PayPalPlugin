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

namespace Sylius\PayPalPlugin\Resolver;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Model\PayPalShippingOptions;

interface PayPalShippingOptionsResolverInterface
{
    public function resolve(OrderInterface $order, AddressInterface $shippingAddress): PayPalShippingOptions;
}
