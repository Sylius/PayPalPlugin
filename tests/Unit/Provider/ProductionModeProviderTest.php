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

namespace Tests\Sylius\PayPalPlugin\Unit\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Provider\ProductionModeProvider;

final class ProductionModeProviderTest extends TestCase
{
    #[Test]
    public function it_is_never_in_sandbox_mode(): void
    {
        self::assertFalse((new ProductionModeProvider())->isSandbox());
    }
}
