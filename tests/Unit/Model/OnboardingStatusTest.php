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
use Sylius\PayPalPlugin\Model\OnboardingStatus;

final class OnboardingStatusTest extends TestCase
{
    public function test_it_tells_which_capabilities_are_active(): void
    {
        $status = new OnboardingStatus(true, true, ['APPLE_PAY']);

        self::assertSame(['APPLE_PAY'], $status->activeCapabilities());
        self::assertTrue($status->hasActiveCapability('APPLE_PAY'));
        self::assertFalse($status->hasActiveCapability('GOOGLE_PAY'));
    }

    public function test_it_has_no_active_capabilities_by_default(): void
    {
        self::assertSame([], (new OnboardingStatus(true, true))->activeCapabilities());
    }

    public function test_it_is_restored_from_its_array_form(): void
    {
        $status = new OnboardingStatus(true, false, ['APPLE_PAY', 'GOOGLE_PAY']);

        self::assertEquals($status, OnboardingStatus::fromArray($status->toArray()));
    }

    public function test_it_is_read_from_a_merchant_integration_with_only_its_active_capabilities(): void
    {
        $status = OnboardingStatus::fromMerchantIntegration([
            'payments_receivable' => true,
            'primary_email_confirmed' => true,
            'capabilities' => [
                ['name' => 'APPLE_PAY', 'status' => 'ACTIVE'],
                ['name' => 'GOOGLE_PAY', 'status' => 'NEED_DATA'],
                ['status' => 'ACTIVE'],
                'CUSTOM_CARD_PROCESSING',
            ],
        ]);

        self::assertTrue($status->isComplete());
        self::assertSame(['APPLE_PAY'], $status->activeCapabilities());
    }

    public function test_it_reads_an_empty_merchant_integration_as_incomplete_without_capabilities(): void
    {
        $status = OnboardingStatus::fromMerchantIntegration(['capabilities' => 'unexpected']);

        self::assertFalse($status->isComplete());
        self::assertSame([], $status->activeCapabilities());
    }
}
