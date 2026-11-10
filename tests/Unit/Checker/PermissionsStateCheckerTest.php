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

namespace Tests\Sylius\PayPalPlugin\Unit\Checker;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Checker\PermissionsStateChecker;
use Sylius\PayPalPlugin\Model\OnboardingScope;
use Sylius\PayPalPlugin\Model\PermissionsState;

final class PermissionsStateCheckerTest extends TestCase
{
    public function test_it_finds_the_permissions_up_to_date_when_the_stored_scope_covers_the_current_one(): void
    {
        self::assertEquals(
            PermissionsState::upToDate(),
            (new PermissionsStateChecker())->check($this->paymentMethod(['onboarding_scope' => OnboardingScope::current()->toArray()])),
        );
    }

    public function test_it_finds_the_permissions_outdated_with_what_the_stored_scope_lacks(): void
    {
        $current = OnboardingScope::current();
        $stored = new OnboardingScope($current->product(), $current->secondaryProducts(), ['APPLE_PAY'], $current->features());

        self::assertEquals(
            PermissionsState::outdated(['GOOGLE_PAY']),
            (new PermissionsStateChecker())->check($this->paymentMethod(['onboarding_scope' => $stored->toArray()])),
        );
    }

    public function test_it_finds_the_permissions_unknown_when_no_scope_is_stored(): void
    {
        self::assertEquals(PermissionsState::unknown(), (new PermissionsStateChecker())->check($this->paymentMethod([])));
    }

    public function test_it_finds_the_permissions_unknown_for_a_payment_method_without_a_gateway_config(): void
    {
        self::assertEquals(PermissionsState::unknown(), (new PermissionsStateChecker())->check(new PaymentMethod()));
    }

    /** @param array<string, mixed> $config */
    private function paymentMethod(array $config): PaymentMethod
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setConfig($config);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        return $paymentMethod;
    }
}
