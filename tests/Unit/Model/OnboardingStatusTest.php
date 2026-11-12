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
        $status = new OnboardingStatus(true, true, ['APPLE_PAY' => 'ACTIVE', 'GOOGLE_PAY' => 'NEED_DATA']);

        self::assertSame(['APPLE_PAY'], $status->activeCapabilities());
        self::assertTrue($status->hasActiveCapability('APPLE_PAY'));
        self::assertFalse($status->hasActiveCapability('GOOGLE_PAY'));
        self::assertFalse($status->hasActiveCapability('VENMO'));
    }

    public function test_it_tells_the_status_of_a_capability(): void
    {
        $status = new OnboardingStatus(true, true, ['GOOGLE_PAY' => 'NEED_DATA']);

        self::assertSame('NEED_DATA', $status->capabilityStatus('GOOGLE_PAY'));
        self::assertNull($status->capabilityStatus('APPLE_PAY'));
    }

    public function test_it_has_no_capabilities_by_default(): void
    {
        $status = new OnboardingStatus(true, true);

        self::assertSame([], $status->activeCapabilities());
        self::assertNull($status->capabilityStatus(OnboardingStatus::VAULTING_CAPABILITY));
        self::assertFalse($status->isVaultingEnabled());
    }

    public function test_it_enables_vaulting_only_when_the_vaulting_capability_is_active(): void
    {
        self::assertTrue((new OnboardingStatus(true, true, ['PAYPAL_WALLET_VAULTING_ADVANCED' => 'ACTIVE']))->isVaultingEnabled());
        self::assertFalse((new OnboardingStatus(true, true, ['PAYPAL_WALLET_VAULTING_ADVANCED' => 'NEED_DATA']))->isVaultingEnabled());
    }

    public function test_it_is_read_from_a_merchant_integration_with_the_status_of_every_capability(): void
    {
        $status = OnboardingStatus::fromMerchantIntegration([
            'payments_receivable' => true,
            'primary_email_confirmed' => true,
            'capabilities' => [
                ['name' => 'APPLE_PAY', 'status' => 'ACTIVE'],
                ['name' => 'GOOGLE_PAY', 'status' => 'NEED_DATA'],
                ['name' => 'PAYPAL_WALLET_VAULTING_ADVANCED', 'status' => 'IN_REVIEW'],
                ['name' => 'VENMO'],
                ['status' => 'ACTIVE'],
                'CUSTOM_CARD_PROCESSING',
            ],
        ]);

        self::assertTrue($status->isComplete());
        self::assertSame(['APPLE_PAY'], $status->activeCapabilities());
        self::assertSame('NEED_DATA', $status->capabilityStatus('GOOGLE_PAY'));
        self::assertSame('IN_REVIEW', $status->capabilityStatus(OnboardingStatus::VAULTING_CAPABILITY));
        self::assertNull($status->capabilityStatus('VENMO'));
        self::assertFalse($status->isVaultingEnabled());
    }

    public function test_it_takes_the_last_status_of_a_capability_the_merchant_integration_lists_more_than_once(): void
    {
        $status = OnboardingStatus::fromMerchantIntegration([
            'capabilities' => [
                ['name' => 'PAYPAL_WALLET_VAULTING_ADVANCED', 'status' => 'ACTIVE'],
                ['name' => 'PAYPAL_WALLET_VAULTING_ADVANCED', 'status' => 'REVOKED'],
            ],
        ]);

        self::assertSame('REVOKED', $status->capabilityStatus(OnboardingStatus::VAULTING_CAPABILITY));
        self::assertFalse($status->isVaultingEnabled());
        self::assertSame([], $status->activeCapabilities());
    }

    public function test_it_skips_the_capabilities_whose_name_or_status_is_not_a_string(): void
    {
        $status = OnboardingStatus::fromMerchantIntegration([
            'capabilities' => [
                ['name' => 'PAYPAL_WALLET_VAULTING_ADVANCED', 'status' => ['code' => 'ACTIVE']],
                ['name' => 123, 'status' => 'ACTIVE'],
                ['name' => 'APPLE_PAY', 'status' => 'ACTIVE'],
            ],
        ]);

        self::assertNull($status->capabilityStatus(OnboardingStatus::VAULTING_CAPABILITY));
        self::assertSame(['APPLE_PAY'], $status->activeCapabilities());
    }

    public function test_it_lists_a_numeric_capability_name_as_a_string(): void
    {
        $status = OnboardingStatus::fromMerchantIntegration([
            'capabilities' => [
                ['name' => '123', 'status' => 'ACTIVE'],
            ],
        ]);

        self::assertSame(['123'], $status->activeCapabilities());
        self::assertTrue($status->hasActiveCapability('123'));
    }

    public function test_it_reads_an_empty_merchant_integration_as_incomplete_without_capabilities(): void
    {
        $status = OnboardingStatus::fromMerchantIntegration(['capabilities' => 'unexpected']);

        self::assertFalse($status->isComplete());
        self::assertSame([], $status->activeCapabilities());
        self::assertNull($status->capabilityStatus(OnboardingStatus::VAULTING_CAPABILITY));
    }
}
