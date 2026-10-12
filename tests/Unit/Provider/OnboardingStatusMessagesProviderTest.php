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
use Sylius\PayPalPlugin\Provider\OnboardingStatusMessagesProvider;

final class OnboardingStatusMessagesProviderTest extends TestCase
{
    #[Test]
    public function it_provides_no_messages_when_onboarding_is_complete(): void
    {
        self::assertSame([], (new OnboardingStatusMessagesProvider())->provide(new OnboardingStatus(true, true)));
    }

    #[Test]
    public function it_provides_a_message_for_each_unmet_requirement(): void
    {
        self::assertSame(
            [
                'sylius_paypal.seller_onboarding_payments_not_receivable',
                'sylius_paypal.seller_onboarding_primary_email_not_confirmed',
            ],
            (new OnboardingStatusMessagesProvider())->provide(new OnboardingStatus(false, false)),
        );
    }

    #[Test]
    public function it_provides_only_the_payments_not_receivable_message(): void
    {
        self::assertSame(
            ['sylius_paypal.seller_onboarding_payments_not_receivable'],
            (new OnboardingStatusMessagesProvider())->provide(new OnboardingStatus(false, true)),
        );
    }

    #[Test]
    public function it_provides_only_the_primary_email_not_confirmed_message(): void
    {
        self::assertSame(
            ['sylius_paypal.seller_onboarding_primary_email_not_confirmed'],
            (new OnboardingStatusMessagesProvider())->provide(new OnboardingStatus(true, false)),
        );
    }
}
