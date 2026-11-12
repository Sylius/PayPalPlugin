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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Model\Capability;
use Sylius\PayPalPlugin\Model\OnboardingScope;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\PermissionsVerification;
use Sylius\PayPalPlugin\Model\PermissionsVerificationOutcome;

final class PermissionsVerificationTest extends TestCase
{
    private const ACTIVE = OnboardingStatus::ACTIVE_CAPABILITY_STATUS;

    private OnboardingScope $scope;

    protected function setUp(): void
    {
        $this->scope = new OnboardingScope('ppcp', ['payment_methods'], [Capability::APPLE_PAY, Capability::GOOGLE_PAY], ['payment']);
    }

    public static function statusesAConsentDoesNotChange(): iterable
    {
        yield 'pending' => ['PENDING'];
        yield 'in review' => ['IN_REVIEW'];
        yield 'need data' => ['NEED_DATA'];
        yield 'suspended' => ['SUSPENDED'];
        yield 'revoked' => ['REVOKED'];
        yield 'deny' => ['DENY'];
    }

    public static function statusesAConsentMayChange(): iterable
    {
        yield 'inactive' => ['INACTIVE'];
        yield 'approved in lowercase' => ['approved'];
        yield 'unknown to the plugin' => ['SOMETHING_NEW'];
    }

    public function test_it_finds_an_account_with_every_capability_of_the_scope_active_up_to_date(): void
    {
        $status = new OnboardingStatus(true, true, [
            Capability::APPLE_PAY => self::ACTIVE,
            strtolower(Capability::GOOGLE_PAY) => self::ACTIVE,
            'VENMO' => 'NEED_DATA',
        ]);

        self::assertEquals(PermissionsVerification::upToDate(), PermissionsVerification::of($status, $this->scope));
    }

    #[DataProvider('statusesAConsentDoesNotChange')]
    public function test_it_awaits_paypal_for_a_capability_whose_status_a_consent_does_not_change(string $capabilityStatus): void
    {
        $status = new OnboardingStatus(true, true, [Capability::APPLE_PAY => self::ACTIVE, Capability::GOOGLE_PAY => $capabilityStatus]);

        self::assertEquals(PermissionsVerification::awaitingPayPal([Capability::GOOGLE_PAY => $capabilityStatus]), PermissionsVerification::of($status, $this->scope));
    }

    #[DataProvider('statusesAConsentMayChange')]
    public function test_it_requires_the_consent_for_a_capability_whose_status_a_consent_may_change(string $capabilityStatus): void
    {
        $status = new OnboardingStatus(true, true, [Capability::APPLE_PAY => self::ACTIVE, Capability::GOOGLE_PAY => $capabilityStatus]);

        self::assertEquals(PermissionsVerification::consentRequired([Capability::GOOGLE_PAY]), PermissionsVerification::of($status, $this->scope));
    }

    public function test_it_requires_the_consent_for_every_capability_the_account_does_not_have(): void
    {
        $status = new OnboardingStatus(true, true, ['VENMO' => self::ACTIVE]);

        self::assertEquals(
            PermissionsVerification::consentRequired([Capability::APPLE_PAY, Capability::GOOGLE_PAY]),
            PermissionsVerification::of($status, $this->scope),
        );
    }

    public function test_it_requires_the_consent_even_when_paypal_is_deciding_on_another_capability(): void
    {
        $status = new OnboardingStatus(true, true, [Capability::APPLE_PAY => 'INACTIVE', Capability::GOOGLE_PAY => 'PENDING']);

        self::assertEquals(PermissionsVerification::consentRequired([Capability::APPLE_PAY]), PermissionsVerification::of($status, $this->scope));
    }

    public function test_it_describes_the_capabilities_paypal_has_not_activated_yet_with_their_status(): void
    {
        $verification = PermissionsVerification::awaitingPayPal([Capability::APPLE_PAY => 'PENDING', Capability::GOOGLE_PAY => 'NEED_DATA']);

        self::assertSame(PermissionsVerificationOutcome::AwaitingPayPal, $verification->outcome());
        self::assertTrue($verification->isAwaitingPayPal());
        self::assertSame(['APPLE_PAY: PENDING', 'GOOGLE_PAY: NEED_DATA'], $verification->describedPendingCapabilities());
    }
}
