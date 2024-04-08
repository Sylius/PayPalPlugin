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

use Ramsey\Uuid\Uuid;

final class UuidProvider implements UuidProviderInterface
{
    public function provide(): string
    {
        return Uuid::uuid4()->toString();
    }
}
