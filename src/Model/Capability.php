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

namespace Sylius\PayPalPlugin\Model;

final class Capability
{
    public const APPLE_PAY = 'APPLE_PAY';

    public const GOOGLE_PAY = 'GOOGLE_PAY';

    private function __construct()
    {
    }
}
