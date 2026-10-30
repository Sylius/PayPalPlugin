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
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\PayPalPlugin\Creator\PayPalOnboardingPaymentMethodCreator;
use Sylius\PayPalPlugin\Creator\PayPalOnboardingPaymentMethodCreatorInterface;
use Sylius\PayPalPlugin\Exception\PaymentMethodAlreadyOnboardedException;
use Sylius\PayPalPlugin\Manager\CredentialsManagerInterface;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\SellerOnboardingResult;
use Sylius\PayPalPlugin\Remover\CachedAccessTokenRemoverInterface;

final class PayPalOnboardingPaymentMethodCreatorTest extends TestCase
{
    private const CREDENTIALS = [
        'client_id' => 'CLIENT-ID',
        'client_secret' => 'CLIENT-SECRET',
        'merchant_id' => 'MERCHANT-ID',
        'partner_attribution_id' => 'sylius-ppcp4p-bn-code',
    ];

    private const STORED_CONFIG = [
        'client_id' => 'CLIENT-ID',
        'client_secret' => 'CLIENT-SECRET',
        'merchant_id' => 'MERCHANT-ID',
        'reports_sftp_password' => null,
        'reports_sftp_username' => null,
        'partner_attribution_id' => 'sylius-ppcp4p-bn-code',
        'sandbox' => false,
    ];

    private FactoryInterface&MockObject $gatewayFactory;

    private FactoryInterface&MockObject $paymentMethodFactory;

    private EntityManagerInterface&MockObject $entityManager;

    /** @var PaymentMethodRepositoryInterface<PaymentMethodInterface>&MockObject */
    private PaymentMethodRepositoryInterface&MockObject $paymentMethodRepository;

    /** @var array<string, PaymentMethodInterface> */
    private array $paymentMethods = [];

    private CredentialsManagerInterface&MockObject $credentialsManager;

    private CachedAccessTokenRemoverInterface&MockObject $cachedAccessTokenRemover;

    private PayPalOnboardingPaymentMethodCreator $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gatewayFactory = $this->createMock(FactoryInterface::class);
        $this->paymentMethodFactory = $this->createMock(FactoryInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $this->paymentMethodRepository
            ->method('findOneBy')
            ->willReturnCallback(fn (array $criteria): ?PaymentMethodInterface => $this->paymentMethods[$criteria['code']] ?? null);
        $this->credentialsManager = $this->createMock(CredentialsManagerInterface::class);
        $this->cachedAccessTokenRemover = $this->createMock(CachedAccessTokenRemoverInterface::class);

        $this->creator = new PayPalOnboardingPaymentMethodCreator(
            $this->gatewayFactory,
            $this->paymentMethodFactory,
            $this->entityManager,
            'sylius-ppcp4p-bn-code',
            $this->paymentMethodRepository,
            $this->credentialsManager,
            $this->cachedAccessTokenRemover,
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

        $this->credentialsManager
            ->expects(self::once())
            ->method('store')
            ->with(
                ['reports_sftp_password' => null, 'reports_sftp_username' => null, 'use_authorize' => false],
                false,
                self::CREDENTIALS,
            )
            ->willReturn(self::STORED_CONFIG);

        $gatewayConfig->expects(self::once())->method('setFactoryName')->with('sylius_paypal');
        $gatewayConfig->expects(self::once())->method('setGatewayName')->with('sylius_paypal');
        $gatewayConfig->expects(self::once())->method('setConfig')->with(self::STORED_CONFIG);
        $gatewayConfig->expects(self::once())->method('setUsePayum')->with(false);

        $paymentMethod->expects(self::once())->method('setCode')->with('PAYPAL');
        $paymentMethod->expects(self::once())->method('setName')->with('PayPal');
        $paymentMethod->expects(self::once())->method('setEnabled')->with(true);
        $paymentMethod->expects(self::once())->method('setPosition')->with(0);
        $this->entityManager->expects(self::once())->method('persist')->with($paymentMethod);
        $this->entityManager->expects(self::never())->method('flush');

        self::assertSame($paymentMethod, $this->creator->create($result));
    }

    #[Test]
    public function it_disables_the_payment_method_when_onboarding_status_is_incomplete(): void
    {
        [, $paymentMethod] = $this->mockFactories();
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
        $gatewayConfig->method('getFactoryName')->willReturn('sylius_paypal');
        $this->paymentMethods['PAYPAL_2'] = $paymentMethod;

        $this->credentialsManager->method('hasCredentials')->with($existingConfig, false)->willReturn(false);
        $this->credentialsManager
            ->expects(self::once())
            ->method('store')
            ->with($existingConfig, false, self::CREDENTIALS)
            ->willReturn(self::STORED_CONFIG);

        $gatewayConfig->expects(self::once())->method('setConfig')->with(self::STORED_CONFIG);
        $gatewayConfig->expects(self::never())->method('setUsePayum');
        $paymentMethod->expects(self::once())->method('setEnabled')->with(true);
        $paymentMethod->expects(self::never())->method('setPosition');
        $this->cachedAccessTokenRemover->expects(self::once())->method('remove')->with($paymentMethod);

        $this->gatewayFactory->expects(self::never())->method('createNew');
        $this->paymentMethodFactory->expects(self::never())->method('createNew');
        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $result = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true));

        self::assertSame($paymentMethod, $this->creator->create($result, false, 'PAYPAL_2'));
    }

    #[Test]
    public function it_stores_sandbox_credentials_in_the_existing_payment_method(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $existingConfig = ['sandbox' => false, 'client_id' => 'PROD-CLIENT-ID'];
        $gatewayConfig->method('getConfig')->willReturn($existingConfig);
        $gatewayConfig->method('getFactoryName')->willReturn('sylius_paypal');
        $this->paymentMethods['PAYPAL_2'] = $paymentMethod;

        $this->credentialsManager->method('hasCredentials')->with($existingConfig, true)->willReturn(false);
        $this->credentialsManager
            ->expects(self::once())
            ->method('store')
            ->with($existingConfig, true, self::CREDENTIALS)
            ->willReturn(self::STORED_CONFIG);

        $gatewayConfig->expects(self::once())->method('setConfig')->with(self::STORED_CONFIG);

        $result = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true));

        self::assertSame($paymentMethod, $this->creator->create($result, true, 'PAYPAL_2'));
    }

    #[Test]
    public function it_refuses_to_replace_the_credentials_of_a_mode_the_payment_method_already_has(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $existingConfig = ['sandbox' => false, 'client_id' => 'PROD-CLIENT-ID'];
        $gatewayConfig->method('getConfig')->willReturn($existingConfig);
        $gatewayConfig->method('getFactoryName')->willReturn('sylius_paypal');
        $this->paymentMethods['PAYPAL_2'] = $paymentMethod;

        $this->credentialsManager->method('hasCredentials')->with($existingConfig, false)->willReturn(true);
        $this->credentialsManager->expects(self::never())->method('store');
        $gatewayConfig->expects(self::never())->method('setConfig');
        $paymentMethod->expects(self::never())->method('setEnabled');
        $this->cachedAccessTokenRemover->expects(self::never())->method('remove');

        $result = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true));

        $this->expectException(PaymentMethodAlreadyOnboardedException::class);

        $this->creator->create($result, false, 'PAYPAL_2');
    }

    #[Test]
    public function it_creates_the_payment_method_of_another_paypal_account_with_the_first_free_code_and_a_matching_name(): void
    {
        [, $paymentMethod] = $this->mockFactories();
        $this->credentialsManager->method('store')->willReturn(self::STORED_CONFIG);
        $this->paymentMethods['PAYPAL'] = $this->createStub(PaymentMethodInterface::class);
        $this->paymentMethods['PAYPAL_2'] = $this->createStub(PaymentMethodInterface::class);

        $paymentMethod->expects(self::once())->method('setCode')->with('PAYPAL_3');
        $paymentMethod->expects(self::once())->method('setName')->with('PayPal 3');
        $this->entityManager->expects(self::once())->method('persist')->with($paymentMethod);

        $result = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true));

        self::assertSame($paymentMethod, $this->creator->create($result));
    }

    #[Test]
    public function it_throws_an_exception_for_the_code_of_no_paypal_payment_method(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn('offline');
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $this->paymentMethods['CASH_ON_DELIVERY'] = $paymentMethod;

        $this->credentialsManager->expects(self::never())->method('store');

        $result = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true));

        $this->expectException(\InvalidArgumentException::class);

        $this->creator->create($result, false, 'CASH_ON_DELIVERY');
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
