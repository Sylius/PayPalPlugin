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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\Component\Payment\Model\PaymentMethodInterface as BasePaymentMethodInterface;
use Sylius\PayPalPlugin\Checker\VaultingCapabilityChecker;
use Sylius\PayPalPlugin\Exception\MerchantIntegrationStatusUnavailableException;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Provider\MerchantIntegrationStatusProviderInterface;

final class VaultingCapabilityCheckerTest extends TestCase
{
    private MerchantIntegrationStatusProviderInterface&MockObject $merchantIntegrationStatusProvider;

    private VaultingCapabilityChecker $checker;

    protected function setUp(): void
    {
        $this->merchantIntegrationStatusProvider = $this->createMock(MerchantIntegrationStatusProviderInterface::class);

        $this->checker = new VaultingCapabilityChecker($this->merchantIntegrationStatusProvider);
    }

    public function test_it_tells_vaulting_is_active_when_paypal_reports_the_capability_active(): void
    {
        $paymentMethod = $this->paymentMethod();

        $this->merchantIntegrationStatusProvider
            ->expects(self::once())
            ->method('provide')
            ->with($paymentMethod)
            ->willReturn(new OnboardingStatus(true, true, ['PAYPAL_WALLET_VAULTING_ADVANCED' => 'ACTIVE']))
        ;

        self::assertTrue($this->checker->isVaultingActive($paymentMethod));
    }

    /** @return iterable<string, array{OnboardingStatus|null}> */
    public static function statusesWithoutActiveVaulting(): iterable
    {
        yield 'capability not active yet' => [new OnboardingStatus(true, true, ['PAYPAL_WALLET_VAULTING_ADVANCED' => 'NEED_DATA'])];
        yield 'capability not reported' => [new OnboardingStatus(true, true, ['APPLE_PAY' => 'ACTIVE'])];
        yield 'no merchant integration' => [null];
    }

    #[DataProvider('statusesWithoutActiveVaulting')]
    public function test_it_tells_vaulting_is_not_active_when_paypal_does_not_report_the_capability_active(?OnboardingStatus $status): void
    {
        $this->merchantIntegrationStatusProvider->method('provide')->willReturn($status);

        self::assertFalse($this->checker->isVaultingActive($this->paymentMethod()));
    }

    public function test_it_lets_an_unavailable_merchant_integration_status_through(): void
    {
        $exception = new MerchantIntegrationStatusUnavailableException('PAYPAL', new \RuntimeException('PayPal is down'));
        $this->merchantIntegrationStatusProvider->method('provide')->willThrowException($exception);

        $this->expectExceptionObject($exception);

        $this->checker->isVaultingActive($this->paymentMethod());
    }

    public function test_it_tells_a_payment_method_without_a_gateway_cannot_vault(): void
    {
        $this->merchantIntegrationStatusProvider->expects(self::never())->method('provide');

        self::assertFalse($this->checker->isVaultingActive(new PaymentMethod()));
    }

    public function test_it_tells_a_payment_method_outside_the_core_cannot_vault(): void
    {
        $this->merchantIntegrationStatusProvider->expects(self::never())->method('provide');

        self::assertFalse($this->checker->isVaultingActive($this->createStub(BasePaymentMethodInterface::class)));
    }

    private function paymentMethod(): PaymentMethod
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius_paypal');
        $gatewayConfig->setConfig([
            'client_id' => 'CLIENT-ID',
            'client_secret' => 'SECRET',
            'merchant_id' => 'MERCHANT-ID',
            'sandbox' => true,
        ]);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        return $paymentMethod;
    }
}
