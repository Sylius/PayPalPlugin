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

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\MerchantOnboardingStatusApiInterface;
use Sylius\PayPalPlugin\Model\AccessToken;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\PartnerCredentials;
use Sylius\PayPalPlugin\Provider\PartnerCredentialsProviderInterface;
use Sylius\PayPalPlugin\Refresher\VaultingCapabilityRefresher;

final class VaultingCapabilityRefresherTest extends TestCase
{
    private AuthorizeClientApiInterface&MockObject $authorizeClientApi;

    private MerchantOnboardingStatusApiInterface&MockObject $merchantOnboardingStatusApi;

    private ObjectManager&MockObject $paymentMethodManager;

    private VaultingCapabilityRefresher $refresher;

    protected function setUp(): void
    {
        $this->authorizeClientApi = $this->createMock(AuthorizeClientApiInterface::class);
        $this->authorizeClientApi->method('authorize')->with('CLIENT-ID', 'SECRET', true)->willReturn(new AccessToken('SELLER-TOKEN', 32400));
        $this->merchantOnboardingStatusApi = $this->createMock(MerchantOnboardingStatusApiInterface::class);
        $this->paymentMethodManager = $this->createMock(ObjectManager::class);

        $partnerCredentialsProvider = $this->createStub(PartnerCredentialsProviderInterface::class);
        $partnerCredentialsProvider->method('provide')->willReturn(new PartnerCredentials('PARTNER-ID', 'PARTNER-CLIENT-ID'));

        $this->refresher = new VaultingCapabilityRefresher(
            $this->authorizeClientApi,
            $this->merchantOnboardingStatusApi,
            $partnerCredentialsProvider,
            $this->paymentMethodManager,
        );
    }

    public function test_it_records_a_vaulting_capability_paypal_has_activated_since(): void
    {
        $paymentMethod = $this->paymentMethod(false);
        $this->merchantOnboardingStatusApi
            ->expects(self::once())
            ->method('get')
            ->with('SELLER-TOKEN', 'PARTNER-ID', 'MERCHANT-ID', true)
            ->willReturn(new OnboardingStatus(true, true, 'ACTIVE'))
        ;
        $this->paymentMethodManager->expects(self::once())->method('flush');

        self::assertTrue($this->refresher->refresh($paymentMethod));
        self::assertTrue($paymentMethod->getGatewayConfig()?->getConfig()['vaulting_enabled']);
    }

    public function test_it_records_a_vaulting_capability_paypal_has_taken_away(): void
    {
        $paymentMethod = $this->paymentMethod(true);
        $this->merchantOnboardingStatusApi->method('get')->willReturn(new OnboardingStatus(true, true, 'SUSPENDED'));
        $this->paymentMethodManager->expects(self::once())->method('flush');

        self::assertFalse($this->refresher->refresh($paymentMethod));
        self::assertFalse($paymentMethod->getGatewayConfig()?->getConfig()['vaulting_enabled']);
    }

    public function test_it_writes_nothing_when_the_capability_did_not_change(): void
    {
        $this->merchantOnboardingStatusApi->method('get')->willReturn(new OnboardingStatus(true, true, 'PENDING'));
        $this->paymentMethodManager->expects(self::never())->method('flush');

        self::assertFalse($this->refresher->refresh($this->paymentMethod(false)));
    }

    public function test_it_cannot_tell_a_payment_method_without_a_gateway_vaults(): void
    {
        $this->merchantOnboardingStatusApi->expects(self::never())->method('get');

        self::assertFalse($this->refresher->refresh(new PaymentMethod()));
    }

    private function paymentMethod(bool $vaultingEnabled): PaymentMethod
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius_paypal');
        $gatewayConfig->setConfig([
            'client_id' => 'CLIENT-ID',
            'client_secret' => 'SECRET',
            'merchant_id' => 'MERCHANT-ID',
            'sandbox' => true,
            'vaulting_enabled' => $vaultingEnabled,
        ]);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        return $paymentMethod;
    }
}
