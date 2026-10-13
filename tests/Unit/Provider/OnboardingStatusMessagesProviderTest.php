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
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Provider\ActiveModeProviderInterface;
use Sylius\PayPalPlugin\Provider\HostProvider;
use Sylius\PayPalPlugin\Provider\OnboardingStatusMessagesProvider;

final class OnboardingStatusMessagesProviderTest extends TestCase
{
    #[Test]
    public function it_provides_no_messages_when_onboarding_is_complete(): void
    {
        self::assertSame([], (new OnboardingStatusMessagesProvider(new HostProvider($this->createStub(ActiveModeProviderInterface::class))))->provide(new OnboardingStatus(true, true)));
    }

    #[Test]
    public function it_provides_a_message_for_each_unmet_requirement(): void
    {
        self::assertSame(
            [
                [
                    'message' => 'sylius_paypal.seller_onboarding_payments_not_receivable',
                    'parameters' => ['%paypal_url%' => 'https://www.paypal.com'],
                ],
                [
                    'message' => 'sylius_paypal.seller_onboarding_primary_email_not_confirmed',
                    'parameters' => ['%business_profile_url%' => 'https://www.paypal.com/businessprofile/settings'],
                ],
            ],
            (new OnboardingStatusMessagesProvider(new HostProvider($this->createStub(ActiveModeProviderInterface::class))))->provide(new OnboardingStatus(false, false)),
        );
    }

    #[Test]
    public function it_provides_only_the_payments_not_receivable_message(): void
    {
        self::assertSame(
            [[
                'message' => 'sylius_paypal.seller_onboarding_payments_not_receivable',
                'parameters' => ['%paypal_url%' => 'https://www.paypal.com'],
            ]],
            (new OnboardingStatusMessagesProvider(new HostProvider($this->createStub(ActiveModeProviderInterface::class))))->provide(new OnboardingStatus(false, true)),
        );
    }

    #[Test]
    public function it_provides_only_the_primary_email_not_confirmed_message(): void
    {
        self::assertSame(
            [[
                'message' => 'sylius_paypal.seller_onboarding_primary_email_not_confirmed',
                'parameters' => ['%business_profile_url%' => 'https://www.paypal.com/businessprofile/settings'],
            ]],
            (new OnboardingStatusMessagesProvider(new HostProvider($this->createStub(ActiveModeProviderInterface::class))))->provide(new OnboardingStatus(true, false)),
        );
    }

    #[Test]
    public function it_links_the_sandbox_business_profile_in_sandbox_mode(): void
    {
        self::assertSame(
            [[
                'message' => 'sylius_paypal.seller_onboarding_primary_email_not_confirmed',
                'parameters' => ['%business_profile_url%' => 'https://www.sandbox.paypal.com/businessprofile/settings'],
            ]],
            (new OnboardingStatusMessagesProvider(new HostProvider($this->createStub(ActiveModeProviderInterface::class))))->provide(new OnboardingStatus(true, false), true),
        );
    }
}
