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
use Sylius\PayPalPlugin\Model\OnboardingScope;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\PermissionsVerification;

final class PermissionsVerificationTest extends TestCase
{
    private OnboardingScope $scope;

    protected function setUp(): void
    {
        $this->scope = new OnboardingScope('ppcp', ['payment_methods'], ['APPLE_PAY', 'GOOGLE_PAY', 'PAY_UPON_INVOICE'], ['payment']);
    }

    public function test_it_finds_an_account_with_every_capability_of_the_scope_active_up_to_date(): void
    {
        $status = new OnboardingStatus(true, true, ['APPLE_PAY' => 'ACTIVE', 'google_pay' => 'ACTIVE', 'PAY_UPON_INVOICE' => 'ACTIVE', 'VENMO' => 'NEED_DATA']);

        self::assertEquals(PermissionsVerification::upToDate(), PermissionsVerification::of($status, $this->scope));
    }

    public function test_it_awaits_paypal_for_the_capabilities_it_has_not_activated_yet(): void
    {
        $status = new OnboardingStatus(true, true, ['APPLE_PAY' => 'ACTIVE', 'GOOGLE_PAY' => 'NEED_DATA', 'PAY_UPON_INVOICE' => 'PENDING']);

        $verification = PermissionsVerification::of($status, $this->scope);

        self::assertTrue($verification->isAwaitingPayPal());
        self::assertSame(['GOOGLE_PAY' => 'NEED_DATA', 'PAY_UPON_INVOICE' => 'PENDING'], $verification->pendingCapabilities());
    }

    public function test_it_requires_the_consent_for_every_capability_the_account_does_not_have(): void
    {
        $status = new OnboardingStatus(true, true, ['GOOGLE_PAY' => 'NEED_DATA']);

        $verification = PermissionsVerification::of($status, $this->scope);

        self::assertTrue($verification->isConsentRequired());
        self::assertSame(['APPLE_PAY', 'PAY_UPON_INVOICE'], $verification->missingCapabilities());
    }
}
