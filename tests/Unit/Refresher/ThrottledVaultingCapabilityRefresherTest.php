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
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Refresher\ThrottledVaultingCapabilityRefresher;
use Sylius\PayPalPlugin\Refresher\VaultingCapabilityRefresherInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class ThrottledVaultingCapabilityRefresherTest extends TestCase
{
    private VaultingCapabilityRefresherInterface&MockObject $innerRefresher;

    private LoggerInterface&MockObject $logger;

    private ThrottledVaultingCapabilityRefresher $refresher;

    protected function setUp(): void
    {
        $this->innerRefresher = $this->createMock(VaultingCapabilityRefresherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->refresher = new ThrottledVaultingCapabilityRefresher($this->innerRefresher, new ArrayAdapter(), $this->logger, 3600);
    }

    public function test_it_asks_paypal_about_a_payment_method_that_cannot_vault_yet(): void
    {
        $paymentMethod = $this->paymentMethod('PAYPAL', false);
        $this->innerRefresher->expects(self::once())->method('refresh')->with($paymentMethod)->willReturn(true);

        self::assertTrue($this->refresher->refresh($paymentMethod));
    }

    public function test_it_asks_paypal_about_a_payment_method_at_most_once_per_cooldown(): void
    {
        $paymentMethod = $this->paymentMethod('PAYPAL', false);
        $this->innerRefresher->expects(self::once())->method('refresh')->willReturn(false);

        self::assertFalse($this->refresher->refresh($paymentMethod));
        self::assertFalse($this->refresher->refresh($paymentMethod));
    }

    public function test_it_keeps_a_separate_cooldown_for_every_payment_method(): void
    {
        $this->innerRefresher->expects(self::exactly(2))->method('refresh')->willReturn(false);

        $this->refresher->refresh($this->paymentMethod('PAYPAL', false));
        $this->refresher->refresh($this->paymentMethod('PAYPAL_EU', false));
    }

    public function test_it_does_not_ask_paypal_about_a_payment_method_that_already_vaults(): void
    {
        $this->innerRefresher->expects(self::never())->method('refresh');

        self::assertTrue($this->refresher->refresh($this->paymentMethod('PAYPAL', true)));
    }

    public function test_it_treats_a_payment_method_paypal_could_not_tell_about_as_not_vaulting(): void
    {
        $this->innerRefresher->method('refresh')->willThrowException(new \RuntimeException('PayPal is down'));
        $this->logger->expects(self::once())->method('warning');

        self::assertFalse($this->refresher->refresh($this->paymentMethod('PAYPAL', false)));
    }

    private function paymentMethod(string $code, bool $vaultingEnabled): PaymentMethod
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius_paypal');
        $gatewayConfig->setConfig(['vaulting_enabled' => $vaultingEnabled]);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setCode($code);
        $paymentMethod->setGatewayConfig($gatewayConfig);

        return $paymentMethod;
    }
}
