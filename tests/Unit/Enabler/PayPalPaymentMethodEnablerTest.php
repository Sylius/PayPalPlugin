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

namespace Tests\Sylius\PayPalPlugin\Unit\Enabler;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\MerchantOnboardingStatusApiInterface;
use Sylius\PayPalPlugin\Enabler\PaymentMethodEnablerInterface;
use Sylius\PayPalPlugin\Enabler\PayPalPaymentMethodEnabler;
use Sylius\PayPalPlugin\Exception\PaymentMethodCouldNotBeEnabledException;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\PartnerCredentials;
use Sylius\PayPalPlugin\Provider\PartnerCredentialsProviderInterface;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;

final class PayPalPaymentMethodEnablerTest extends TestCase
{
    private AuthorizeClientApiInterface&MockObject $authorizeClientApi;

    private MerchantOnboardingStatusApiInterface&MockObject $merchantOnboardingStatusApi;

    private ObjectManager&MockObject $paymentMethodManager;

    private SellerWebhookRegistrarInterface&MockObject $sellerWebhookRegistrar;

    private PartnerCredentialsProviderInterface&MockObject $partnerCredentialsProvider;

    private PayPalPaymentMethodEnabler $payPalPaymentMethodEnabler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorizeClientApi = $this->createMock(AuthorizeClientApiInterface::class);
        $this->merchantOnboardingStatusApi = $this->createMock(MerchantOnboardingStatusApiInterface::class);
        $this->paymentMethodManager = $this->createMock(ObjectManager::class);
        $this->sellerWebhookRegistrar = $this->createMock(SellerWebhookRegistrarInterface::class);
        $this->partnerCredentialsProvider = $this->createMock(PartnerCredentialsProviderInterface::class);
        $this->partnerCredentialsProvider
            ->method('provide')
            ->willReturn(new PartnerCredentials('PARTNER-ID', 'PARTNER-CLIENT-ID'));

        $this->payPalPaymentMethodEnabler = new PayPalPaymentMethodEnabler(
            $this->authorizeClientApi,
            $this->merchantOnboardingStatusApi,
            $this->paymentMethodManager,
            $this->sellerWebhookRegistrar,
            $this->partnerCredentialsProvider,
        );
    }

    #[Test]
    public function it_implements_payment_method_enabler_interface(): void
    {
        self::assertInstanceOf(PaymentMethodEnablerInterface::class, $this->payPalPaymentMethodEnabler);
    }

    #[Test]
    public function it_enables_payment_method_when_onboarding_is_complete(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $gatewayConfig
            ->method('getConfig')
            ->willReturn(['merchant_id' => 'MERCHANT-ID', 'client_id' => 'CLIENT-ID', 'client_secret' => 'SECRET']);

        $this->authorizeClientApi
            ->expects(self::once())
            ->method('authorize')
            ->with('CLIENT-ID', 'SECRET')
            ->willReturn('SELLER-TOKEN');

        $this->merchantOnboardingStatusApi
            ->expects(self::once())
            ->method('get')
            ->with('SELLER-TOKEN', 'PARTNER-ID', 'MERCHANT-ID')
            ->willReturn(new OnboardingStatus(true, true));

        $this->sellerWebhookRegistrar
            ->expects(self::once())
            ->method('register')
            ->with($paymentMethod);

        $paymentMethod->expects(self::once())->method('setEnabled')->with(true);
        $this->paymentMethodManager->expects(self::once())->method('flush');

        $this->payPalPaymentMethodEnabler->enable($paymentMethod);
    }

    #[Test]
    public function it_throws_exception_when_onboarding_is_not_complete(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $gatewayConfig
            ->method('getConfig')
            ->willReturn(['merchant_id' => 'MERCHANT-ID', 'client_id' => 'CLIENT-ID', 'client_secret' => 'SECRET']);

        $this->authorizeClientApi->method('authorize')->willReturn('SELLER-TOKEN');
        $this->merchantOnboardingStatusApi->method('get')->willReturn(new OnboardingStatus(false, true));

        $this->sellerWebhookRegistrar->expects(self::never())->method('register');
        $paymentMethod->expects(self::never())->method('setEnabled');
        $this->paymentMethodManager->expects(self::never())->method('flush');

        $this->expectException(PaymentMethodCouldNotBeEnabledException::class);

        $this->payPalPaymentMethodEnabler->enable($paymentMethod);
    }
}
