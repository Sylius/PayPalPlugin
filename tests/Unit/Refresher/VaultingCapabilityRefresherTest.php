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

namespace Tests\Sylius\PayPalPlugin\Unit\Refresher;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Checker\VaultingCapabilityCheckerInterface;
use Sylius\PayPalPlugin\Refresher\VaultingCapabilityRefresher;

final class VaultingCapabilityRefresherTest extends TestCase
{
    private VaultingCapabilityCheckerInterface&MockObject $checker;

    private VaultingCapabilityRefresher $refresher;

    protected function setUp(): void
    {
        $this->checker = $this->createMock(VaultingCapabilityCheckerInterface::class);

        $this->refresher = new VaultingCapabilityRefresher($this->checker);
    }

    public function test_it_records_a_vaulting_capability_paypal_has_activated_since(): void
    {
        $paymentMethod = $this->paymentMethod(['vaulting_enabled' => false]);
        $this->checker->expects(self::once())->method('isVaultingActive')->with($paymentMethod)->willReturn(true);

        self::assertTrue($this->refresher->refresh($paymentMethod));
        self::assertTrue($paymentMethod->getGatewayConfig()?->getConfig()['vaulting_enabled']);
    }

    public function test_it_records_a_vaulting_capability_paypal_has_taken_away(): void
    {
        $paymentMethod = $this->paymentMethod(['vaulting_enabled' => true]);
        $this->checker->method('isVaultingActive')->willReturn(false);

        self::assertFalse($this->refresher->refresh($paymentMethod));
        self::assertFalse($paymentMethod->getGatewayConfig()?->getConfig()['vaulting_enabled']);
    }

    public function test_it_leaves_the_gateway_config_alone_when_the_capability_did_not_change(): void
    {
        $paymentMethod = $this->paymentMethod([]);
        $this->checker->method('isVaultingActive')->willReturn(false);

        self::assertFalse($this->refresher->refresh($paymentMethod));
        self::assertArrayNotHasKey('vaulting_enabled', $paymentMethod->getGatewayConfig()?->getConfig() ?? []);
    }

    public function test_it_cannot_tell_a_payment_method_without_a_gateway_vaults(): void
    {
        $this->checker->expects(self::never())->method('isVaultingActive');

        self::assertFalse($this->refresher->refresh(new PaymentMethod()));
    }

    /** @param array<string, mixed> $config */
    private function paymentMethod(array $config): PaymentMethod
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius_paypal');
        $gatewayConfig->setConfig(['client_id' => 'CLIENT-ID', 'client_secret' => 'SECRET', 'merchant_id' => 'MERCHANT-ID'] + $config);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        return $paymentMethod;
    }
}
