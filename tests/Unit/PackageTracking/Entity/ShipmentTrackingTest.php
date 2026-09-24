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

namespace Tests\Sylius\PayPalPlugin\Unit\PackageTracking\Entity;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\PackageTracking\Entity\ShipmentTracking;
use Sylius\PayPalPlugin\PackageTracking\Entity\ShipmentTrackingInterface;

final class ShipmentTrackingTest extends TestCase
{
    #[Test]
    public function it_starts_pending_with_no_attempts_and_is_not_touched_yet(): void
    {
        $tracking = $this->tracking();

        self::assertSame(ShipmentTrackingInterface::STATE_PENDING, $tracking->getState());
        self::assertSame(0, $tracking->getAttempts());
        self::assertNull($tracking->getUpdatedAt());
        self::assertNotNull($tracking->getCreatedAt());
    }

    #[Test]
    public function it_counts_every_attempt_and_records_when_it_happened(): void
    {
        $tracking = $this->tracking();

        $tracking->markAsFailed('PayPal is down');
        $tracking->markAsSynced('TRK-1');

        self::assertSame(2, $tracking->getAttempts());
        self::assertSame(ShipmentTrackingInterface::STATE_SYNCED, $tracking->getState());
        self::assertNull($tracking->getLastError());
        self::assertNotNull($tracking->getUpdatedAt());
    }

    #[Test]
    public function it_does_not_count_being_reset_to_pending_as_an_attempt(): void
    {
        $tracking = $this->tracking();

        $tracking->markAsPending();

        self::assertSame(0, $tracking->getAttempts());
        self::assertNotNull($tracking->getUpdatedAt());
    }

    private function tracking(): ShipmentTracking
    {
        return new ShipmentTracking($this->createMock(ShipmentInterface::class));
    }
}
