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

namespace Tests\Sylius\PayPalPlugin\Unit\Creator;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\PayPalPlugin\Creator\PayPalOnboardingPaymentMethodCreator;
use Sylius\PayPalPlugin\Creator\PayPalOnboardingPaymentMethodCreatorInterface;
use Sylius\PayPalPlugin\Manager\PayPalCredentialsManagerInterface;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\SellerOnboardingResult;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;

final class PayPalOnboardingPaymentMethodCreatorTest extends TestCase
{
    private const CREDENTIALS = [
        'client_id' => 'CLIENT-ID',
        'client_secret' => 'CLIENT-SECRET',
        'merchant_id' => 'MERCHANT-ID',
        'sylius_merchant_id' => 'MERCHANT-ID',
        'partner_attribution_id' => 'sylius-ppcp4p-bn-code',
    ];

    private const STORED_CONFIG = [
        'client_id' => 'CLIENT-ID',
        'client_secret' => 'CLIENT-SECRET',
        'merchant_id' => 'MERCHANT-ID',
        'use_authorize' => 1,
        'sylius_merchant_id' => 'MERCHANT-ID',
        'reports_sftp_password' => null,
        'reports_sftp_username' => null,
        'partner_attribution_id' => 'sylius-ppcp4p-bn-code',
        'sandbox' => false,
    ];

    private FactoryInterface&MockObject $gatewayFactory;

    private FactoryInterface&MockObject $paymentMethodFactory;

    private EntityManagerInterface&MockObject $entityManager;

    private PayPalPaymentMethodProviderInterface&MockObject $payPalPaymentMethodProvider;

    private PayPalCredentialsManagerInterface&MockObject $credentialsManager;

    private PayPalOnboardingPaymentMethodCreator $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gatewayFactory = $this->createMock(FactoryInterface::class);
        $this->paymentMethodFactory = $this->createMock(FactoryInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->payPalPaymentMethodProvider = $this->createMock(PayPalPaymentMethodProviderInterface::class);
        $this->credentialsManager = $this->createMock(PayPalCredentialsManagerInterface::class);

        $this->creator = new PayPalOnboardingPaymentMethodCreator(
            $this->gatewayFactory,
            $this->paymentMethodFactory,
            $this->entityManager,
            'sylius-ppcp4p-bn-code',
            $this->payPalPaymentMethodProvider,
            $this->credentialsManager,
        );
    }

    #[Test]
    public function it_implements_paypal_onboarding_payment_method_creator_interface(): void
    {
        self::assertInstanceOf(PayPalOnboardingPaymentMethodCreatorInterface::class, $this->creator);
    }

    #[Test]
    public function it_creates_an_enabled_payment_method_when_onboarding_is_complete(): void
    {
        [$gatewayConfig, $paymentMethod] = $this->mockFactories();
        $this->payPalPaymentMethodProvider->method('exists')->willReturn(false);

        $result = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true));

        $this->credentialsManager
            ->expects(self::once())
            ->method('store')
            ->with(
                ['use_authorize' => 1, 'reports_sftp_password' => null, 'reports_sftp_username' => null],
                false,
                self::CREDENTIALS,
            )
            ->willReturn(self::STORED_CONFIG);

        $gatewayConfig->expects(self::once())->method('setFactoryName')->with('sylius_paypal');
        $gatewayConfig->expects(self::once())->method('setGatewayName')->with('sylius_paypal');
        $gatewayConfig->expects(self::once())->method('setConfig')->with(self::STORED_CONFIG);

        $paymentMethod->expects(self::once())->method('setEnabled')->with(true);
        $this->entityManager->expects(self::once())->method('persist')->with($paymentMethod);
        $this->entityManager->expects(self::never())->method('flush');

        self::assertSame($paymentMethod, $this->creator->create($result));
    }

    #[Test]
    public function it_disables_the_payment_method_when_onboarding_status_is_incomplete(): void
    {
        [, $paymentMethod] = $this->mockFactories();
        $this->payPalPaymentMethodProvider->method('exists')->willReturn(false);
        $this->credentialsManager->method('store')->willReturn(self::STORED_CONFIG);

        $result = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(false, true));

        $paymentMethod->expects(self::once())->method('setEnabled')->with(false);

        self::assertSame($paymentMethod, $this->creator->create($result));
    }

    #[Test]
    public function it_stores_production_credentials_in_the_existing_payment_method(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $existingConfig = ['sandbox' => true, 'client_id' => 'SANDBOX-CLIENT-ID'];
        $gatewayConfig->method('getConfig')->willReturn($existingConfig);

        $this->payPalPaymentMethodProvider->method('exists')->willReturn(true);
        $this->payPalPaymentMethodProvider->method('provide')->willReturn($paymentMethod);

        $this->credentialsManager
            ->expects(self::once())
            ->method('store')
            ->with($existingConfig, false, self::CREDENTIALS)
            ->willReturn(self::STORED_CONFIG);

        $gatewayConfig->expects(self::once())->method('setConfig')->with(self::STORED_CONFIG);
        $paymentMethod->expects(self::once())->method('setEnabled')->with(true);

        $this->gatewayFactory->expects(self::never())->method('createNew');
        $this->paymentMethodFactory->expects(self::never())->method('createNew');
        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $result = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true));

        self::assertSame($paymentMethod, $this->creator->create($result));
    }

    /** @return array{0: GatewayConfigInterface&MockObject, 1: PaymentMethodInterface&MockObject} */
    private function mockFactories(): array
    {
        /** @var GatewayConfigInterface&MockObject $gatewayConfig */
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        /** @var PaymentMethodInterface&MockObject $paymentMethod */
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $this->gatewayFactory->method('createNew')->willReturn($gatewayConfig);
        $this->paymentMethodFactory->method('createNew')->willReturn($paymentMethod);
        $paymentMethod->method('setGatewayConfig');

        return [$gatewayConfig, $paymentMethod];
    }
}
