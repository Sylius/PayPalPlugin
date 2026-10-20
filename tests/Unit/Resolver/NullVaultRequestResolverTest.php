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

namespace Tests\Sylius\PayPalPlugin\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Resolver\NullVaultRequestResolver;

final class NullVaultRequestResolverTest extends TestCase
{
    public function test_it_vaults_nothing(): void
    {
        self::assertNull((new NullVaultRequestResolver())->resolve($this->createStub(OrderInterface::class)));
    }
}
