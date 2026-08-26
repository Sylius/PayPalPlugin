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
use Sylius\PayPalPlugin\Exception\PayPalWebhookUrlNotValidException;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\SellerOnboardingResult;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;

final class PayPalOnboardingPaymentMethodCreatorTest extends TestCase
{
    private SellerWebhookRegistrarInterface&MockObject $sellerWebhookRegistrar;

    private FactoryInterface&MockObject $gatewayFactory;

    private FactoryInterface&MockObject $paymentMethodFactory;

    private EntityManagerInterface&MockObject $entityManager;

    private PayPalOnboardingPaymentMethodCreator $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sellerWebhookRegistrar = $this->createMock(SellerWebhookRegistrarInterface::class);
        $this->gatewayFactory = $this->createMock(FactoryInterface::class);
        $this->paymentMethodFactory = $this->createMock(FactoryInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->creator = new PayPalOnboardingPaymentMethodCreator(
            $this->sellerWebhookRegistrar,
            $this->gatewayFactory,
            $this->paymentMethodFactory,
            $this->entityManager,
            'sylius-ppcp4p-bn-code',
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

        $result = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true));

        $gatewayConfig->expects(self::once())->method('setFactoryName')->with('sylius_paypal');
        $gatewayConfig->expects(self::once())->method('setGatewayName')->with('sylius_paypal');
        $gatewayConfig->expects(self::once())->method('setConfig')->with([
            'client_id' => 'CLIENT-ID',
            'client_secret' => 'CLIENT-SECRET',
            'merchant_id' => 'MERCHANT-ID',
            'use_authorize' => 1,
            'sylius_merchant_id' => 'MERCHANT-ID',
            'reports_sftp_password' => null,
            'reports_sftp_username' => null,
            'partner_attribution_id' => 'sylius-ppcp4p-bn-code',
        ]);

        $paymentMethod->expects(self::never())->method('setEnabled');
        $this->sellerWebhookRegistrar->expects(self::once())->method('register')->with($paymentMethod);
        $this->entityManager->expects(self::once())->method('persist')->with($paymentMethod);
        $this->entityManager->expects(self::once())->method('flush');

        $resultPaymentMethod = $this->creator->create($result);

        self::assertSame($paymentMethod, $resultPaymentMethod);
    }

    #[Test]
    public function it_disables_the_payment_method_when_onboarding_status_is_incomplete(): void
    {
        [, $paymentMethod] = $this->mockFactories();

        $result = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(false, true));

        $paymentMethod->expects(self::once())->method('setEnabled')->with(false);
        $this->sellerWebhookRegistrar->expects(self::once())->method('register')->with($paymentMethod);

        $resultPaymentMethod = $this->creator->create($result);

        self::assertSame($paymentMethod, $resultPaymentMethod);
    }

    #[Test]
    public function it_disables_the_payment_method_when_webhook_url_is_not_valid(): void
    {
        [, $paymentMethod] = $this->mockFactories();

        $result = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true));

        $this->sellerWebhookRegistrar
            ->method('register')
            ->willThrowException(new PayPalWebhookUrlNotValidException());

        $paymentMethod->expects(self::once())->method('setEnabled')->with(false);

        $resultPaymentMethod = $this->creator->create($result);

        self::assertSame($paymentMethod, $resultPaymentMethod);
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
