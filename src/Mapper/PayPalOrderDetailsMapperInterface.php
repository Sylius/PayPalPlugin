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

namespace Sylius\PayPalPlugin\Mapper;

use Sylius\PayPalPlugin\Model\PayPalAddress;
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;

interface PayPalOrderDetailsMapperInterface
{
    /** @param array<string, mixed> $payPalOrder */
    public function map(array $payPalOrder): PayPalOrderDetails;

    /** @param array<string, mixed> $payPalAddress */
    public function mapAddress(array $payPalAddress): PayPalAddress;
}
