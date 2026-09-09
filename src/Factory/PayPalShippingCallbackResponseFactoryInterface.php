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

use Sylius\PayPalPlugin\Model\PayPalShippingOption;

interface PayPalShippingCallbackResponseFactoryInterface
{
    /**
     * @param array<string, mixed> $purchaseUnit
     * @param array<int, PayPalShippingOption> $shippingOptions
     *
     * @return array<string, mixed>
     */
    public function create(string $payPalOrderId, array $purchaseUnit, array $shippingOptions): array;
}
