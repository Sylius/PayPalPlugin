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

final readonly class NonceProvider implements NonceProviderInterface
{
    public function provide(): string
    {
        return bin2hex(random_bytes(16));
    }
}
