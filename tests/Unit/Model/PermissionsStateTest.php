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

namespace Tests\Sylius\PayPalPlugin\Unit\Model;

use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Model\Capability;
use Sylius\PayPalPlugin\Model\PermissionsState;
use Sylius\PayPalPlugin\Model\PermissionsStatus;

final class PermissionsStateTest extends TestCase
{
    public function test_it_tells_up_to_date_permissions_apart(): void
    {
        $state = PermissionsState::upToDate();

        self::assertTrue($state->isUpToDate());
        self::assertSame(PermissionsStatus::UpToDate, $state->status());
        self::assertTrue($state->isKnown());
        self::assertSame([], $state->missing());
    }

    public function test_it_tells_unknown_permissions_apart(): void
    {
        $state = PermissionsState::unknown();

        self::assertFalse($state->isUpToDate());
        self::assertSame(PermissionsStatus::Unknown, $state->status());
        self::assertFalse($state->isKnown());
        self::assertSame([], $state->missing());
    }

    public function test_it_tells_outdated_permissions_apart_with_what_they_miss(): void
    {
        $state = PermissionsState::outdated([Capability::GOOGLE_PAY]);

        self::assertFalse($state->isUpToDate());
        self::assertSame(PermissionsStatus::OutOfDate, $state->status());
        self::assertTrue($state->isKnown());
        self::assertSame([Capability::GOOGLE_PAY], $state->missing());
    }
}
