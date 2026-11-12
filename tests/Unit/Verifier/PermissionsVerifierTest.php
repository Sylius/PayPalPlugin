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

namespace Tests\Sylius\PayPalPlugin\Unit\Verifier;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\PayPalPlugin\Exception\MerchantIntegrationStatusUnavailableException;
use Sylius\PayPalPlugin\Model\Capability;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\PermissionsVerification;
use Sylius\PayPalPlugin\Provider\MerchantIntegrationStatusProviderInterface;
use Sylius\PayPalPlugin\Updater\OnboardingScopeUpdaterInterface;
use Sylius\PayPalPlugin\Verifier\PermissionsVerifier;

final class PermissionsVerifierTest extends TestCase
{
    private MerchantIntegrationStatusProviderInterface&MockObject $merchantIntegrationStatusProvider;

    private OnboardingScopeUpdaterInterface&MockObject $onboardingScopeUpdater;

    private PermissionsVerifier $verifier;

    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        $this->merchantIntegrationStatusProvider = $this->createMock(MerchantIntegrationStatusProviderInterface::class);
        $this->onboardingScopeUpdater = $this->createMock(OnboardingScopeUpdaterInterface::class);
        $this->verifier = new PermissionsVerifier($this->merchantIntegrationStatusProvider, $this->onboardingScopeUpdater);
        $this->paymentMethod = new PaymentMethod();
    }

    public function test_it_stores_the_current_scope_of_an_account_with_every_capability_active(): void
    {
        $this->givenStatus([Capability::APPLE_PAY => OnboardingStatus::ACTIVE_CAPABILITY_STATUS, Capability::GOOGLE_PAY => OnboardingStatus::ACTIVE_CAPABILITY_STATUS, 'CUSTOM_CARD_PROCESSING' => OnboardingStatus::ACTIVE_CAPABILITY_STATUS]);

        $this->onboardingScopeUpdater->expects(self::once())->method('update')->with($this->paymentMethod);

        self::assertEquals(PermissionsVerification::upToDate(), $this->verifier->verify($this->paymentMethod));
    }

    public function test_it_waits_for_paypal_to_activate_a_capability_the_account_already_asked_for(): void
    {
        $this->givenStatus([Capability::APPLE_PAY => OnboardingStatus::ACTIVE_CAPABILITY_STATUS, Capability::GOOGLE_PAY => 'NEED_DATA']);

        $this->onboardingScopeUpdater->expects(self::never())->method('update');

        self::assertEquals(
            PermissionsVerification::awaitingPayPal([Capability::GOOGLE_PAY => 'NEED_DATA']),
            $this->verifier->verify($this->paymentMethod),
        );
    }

    public function test_it_requires_the_consent_for_a_capability_the_account_does_not_have(): void
    {
        $this->givenStatus([Capability::APPLE_PAY => 'PENDING']);

        $this->onboardingScopeUpdater->expects(self::never())->method('update');

        self::assertEquals(PermissionsVerification::consentRequired([Capability::GOOGLE_PAY]), $this->verifier->verify($this->paymentMethod));
    }

    public function test_it_requires_the_consent_of_an_account_the_configured_partner_does_not_know(): void
    {
        $this->merchantIntegrationStatusProvider->method('provide')->willReturn(null);

        self::assertEquals(PermissionsVerification::consentRequired(), $this->verifier->verify($this->paymentMethod));
    }

    public function test_it_lets_an_unreadable_status_through(): void
    {
        $this->merchantIntegrationStatusProvider->method('provide')->willThrowException(
            new MerchantIntegrationStatusUnavailableException('PAYPAL', new \RuntimeException('PayPal is down')),
        );

        $this->onboardingScopeUpdater->expects(self::never())->method('update');

        $this->expectException(MerchantIntegrationStatusUnavailableException::class);

        $this->verifier->verify($this->paymentMethod);
    }

    public function test_it_verifies_a_given_status_without_asking_paypal_and_stores_the_current_scope_when_it_is_up_to_date(): void
    {
        $this->merchantIntegrationStatusProvider->expects(self::never())->method('provide');
        $this->onboardingScopeUpdater->expects(self::once())->method('update')->with($this->paymentMethod);

        self::assertEquals(
            PermissionsVerification::upToDate(),
            $this->verifier->verifyStatus($this->paymentMethod, new OnboardingStatus(true, true, [
                Capability::APPLE_PAY => OnboardingStatus::ACTIVE_CAPABILITY_STATUS,
                Capability::GOOGLE_PAY => OnboardingStatus::ACTIVE_CAPABILITY_STATUS,
            ])),
        );
    }

    public function test_it_keeps_the_stored_scope_when_a_given_status_is_not_up_to_date(): void
    {
        $this->onboardingScopeUpdater->expects(self::never())->method('update');

        self::assertEquals(
            PermissionsVerification::consentRequired([Capability::GOOGLE_PAY]),
            $this->verifier->verifyStatus($this->paymentMethod, new OnboardingStatus(true, true, [Capability::APPLE_PAY => OnboardingStatus::ACTIVE_CAPABILITY_STATUS])),
        );
    }

    /** @param array<string, string> $capabilityStatuses */
    private function givenStatus(array $capabilityStatuses): void
    {
        $this->merchantIntegrationStatusProvider
            ->method('provide')
            ->with($this->paymentMethod)
            ->willReturn(new OnboardingStatus(true, true, $capabilityStatuses))
        ;
    }
}
