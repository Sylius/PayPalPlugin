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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\MerchantOnboardingStatusApiInterface;
use Sylius\PayPalPlugin\Checker\VaultingCapabilityChecker;
use Sylius\PayPalPlugin\Model\AccessToken;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\PartnerCredentials;
use Sylius\PayPalPlugin\Provider\PartnerCredentialsProviderInterface;

final class VaultingCapabilityCheckerTest extends TestCase
{
    private MerchantOnboardingStatusApiInterface&MockObject $merchantOnboardingStatusApi;

    private VaultingCapabilityChecker $checker;

    protected function setUp(): void
    {
        $authorizeClientApi = $this->createStub(AuthorizeClientApiInterface::class);
        $authorizeClientApi->method('authorize')->with('CLIENT-ID', 'SECRET', true)->willReturn(new AccessToken('SELLER-TOKEN', 32400));
        $this->merchantOnboardingStatusApi = $this->createMock(MerchantOnboardingStatusApiInterface::class);

        $partnerCredentialsProvider = $this->createStub(PartnerCredentialsProviderInterface::class);
        $partnerCredentialsProvider->method('provide')->willReturn(new PartnerCredentials('PARTNER-ID', 'PARTNER-CLIENT-ID'));

        $this->checker = new VaultingCapabilityChecker($authorizeClientApi, $this->merchantOnboardingStatusApi, $partnerCredentialsProvider);
    }

    public function test_it_tells_vaulting_is_active_when_paypal_reports_the_capability_active(): void
    {
        $this->merchantOnboardingStatusApi
            ->expects(self::once())
            ->method('get')
            ->with('SELLER-TOKEN', 'PARTNER-ID', 'MERCHANT-ID', true)
            ->willReturn(new OnboardingStatus(true, true, [], 'ACTIVE'))
        ;

        self::assertTrue($this->checker->isVaultingActive($this->paymentMethod()));
    }

    public function test_it_tells_vaulting_is_not_active_when_paypal_reports_another_status(): void
    {
        $this->merchantOnboardingStatusApi->method('get')->willReturn(new OnboardingStatus(true, true, [], 'NEED_DATA'));

        self::assertFalse($this->checker->isVaultingActive($this->paymentMethod()));
    }

    public function test_it_leaves_the_gateway_config_alone(): void
    {
        $paymentMethod = $this->paymentMethod();
        $config = $paymentMethod->getGatewayConfig()?->getConfig();
        $this->merchantOnboardingStatusApi->method('get')->willReturn(new OnboardingStatus(true, true, [], 'ACTIVE'));

        $this->checker->isVaultingActive($paymentMethod);

        self::assertSame($config, $paymentMethod->getGatewayConfig()?->getConfig());
    }

    public function test_it_tells_a_payment_method_without_a_gateway_cannot_vault(): void
    {
        $this->merchantOnboardingStatusApi->expects(self::never())->method('get');

        self::assertFalse($this->checker->isVaultingActive(new PaymentMethod()));
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
            'vaulting_enabled' => false,
        ]);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        return $paymentMethod;
    }
}
