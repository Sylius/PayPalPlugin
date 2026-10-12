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

namespace Tests\Sylius\PayPalPlugin\Unit\Onboarding\Checker;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Manager\CredentialsManager;
use Sylius\PayPalPlugin\Onboarding\Checker\ProductionSellerOnboardedChecker;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;

final class ProductionSellerOnboardedCheckerTest extends TestCase
{
    private PayPalPaymentMethodProviderInterface&MockObject $payPalPaymentMethodProvider;

    private ProductionSellerOnboardedChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payPalPaymentMethodProvider = $this->createMock(PayPalPaymentMethodProviderInterface::class);

        $this->checker = new ProductionSellerOnboardedChecker($this->payPalPaymentMethodProvider, new CredentialsManager());
    }

    #[Test]
    public function it_is_not_onboarded_when_there_is_no_paypal_payment_method(): void
    {
        $this->payPalPaymentMethodProvider->method('exists')->willReturn(false);
        $this->payPalPaymentMethodProvider->expects(self::never())->method('provide');

        self::assertFalse($this->checker->isOnboarded());
    }

    #[Test]
    public function it_is_onboarded_when_production_credentials_are_stored(): void
    {
        $this->mockExistingPaymentMethod(['sandbox' => true, 'production_credentials' => ['client_id' => 'PROD']]);

        self::assertTrue($this->checker->isOnboarded());
    }

    #[Test]
    public function it_is_onboarded_when_the_active_credentials_of_a_config_without_mode_are_set(): void
    {
        $this->mockExistingPaymentMethod(['client_id' => 'PROD']);

        self::assertTrue($this->checker->isOnboarded());
    }

    #[Test]
    public function it_is_not_onboarded_when_only_sandbox_credentials_are_stored(): void
    {
        $this->mockExistingPaymentMethod(['sandbox' => true, 'client_id' => 'SB', 'sandbox_credentials' => ['client_id' => 'SB']]);

        self::assertFalse($this->checker->isOnboarded());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function mockExistingPaymentMethod(array $config): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn($config);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $this->payPalPaymentMethodProvider->method('exists')->willReturn(true);
        $this->payPalPaymentMethodProvider->method('provide')->willReturn($paymentMethod);
    }
}
