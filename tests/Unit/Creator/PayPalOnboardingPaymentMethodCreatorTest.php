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
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\PayPalPlugin\Creator\PayPalOnboardingPaymentMethodCreator;
use Sylius\PayPalPlugin\Creator\PayPalOnboardingPaymentMethodCreatorInterface;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\SellerOnboardingResult;

final class PayPalOnboardingPaymentMethodCreatorTest extends TestCase
{
    private const CONFIG = [
        'client_id' => 'CLIENT-ID',
        'client_secret' => 'CLIENT-SECRET',
        'merchant_id' => 'MERCHANT-ID',
        'partner_attribution_id' => 'sylius-ppcp4p-bn-code',
        'sandbox' => false,
        'use_authorize' => false,
        'vaulting_enabled' => false,
    ];

    private FactoryInterface&MockObject $gatewayFactory;

    private FactoryInterface&MockObject $paymentMethodFactory;

    private EntityManagerInterface&MockObject $entityManager;

    /** @var PaymentMethodRepositoryInterface<PaymentMethodInterface>&MockObject */
    private PaymentMethodRepositoryInterface&MockObject $paymentMethodRepository;

    /** @var array<string, PaymentMethodInterface> */
    private array $paymentMethods = [];

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

        $this->creator = new PayPalOnboardingPaymentMethodCreator(
            $this->gatewayFactory,
            $this->paymentMethodFactory,
            $this->entityManager,
            'sylius-ppcp4p-bn-code',
            $this->paymentMethodRepository,
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
        $gatewayConfig->expects(self::once())->method('setConfig')->with(self::CONFIG);

        $paymentMethod->expects(self::once())->method('setCode')->with('PAYPAL');
        $paymentMethod->expects(self::once())->method('setName')->with('PayPal');
        $paymentMethod->expects(self::once())->method('setEnabled')->with(true);
        $paymentMethod->expects(self::once())->method('setPosition')->with(0);
        $this->entityManager->expects(self::once())->method('persist')->with($paymentMethod);
        $this->entityManager->expects(self::never())->method('flush');

        self::assertSame($paymentMethod, $this->creator->create($result, false));
    }

    #[Test]
    public function it_stores_the_vaulting_flag_when_the_vaulting_capability_is_active(): void
    {
        [$gatewayConfig] = $this->mockFactories();

        $gatewayConfig->expects(self::once())->method('setConfig')->with(array_merge(self::CONFIG, ['vaulting_enabled' => true]));

        $this->creator->create(new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true, [], 'ACTIVE')), false);
    }

    #[Test]
    public function it_disables_the_payment_method_when_onboarding_status_is_incomplete(): void
    {
        [, $paymentMethod] = $this->mockFactories();

        $result = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(false, true));

        $paymentMethod->expects(self::once())->method('setEnabled')->with(false);

        self::assertSame($paymentMethod, $this->creator->create($result, false));
    }

    #[Test]
    public function it_creates_a_sandbox_payment_method_next_to_a_production_one(): void
    {
        [$gatewayConfig, $paymentMethod] = $this->mockFactories();
        $this->paymentMethods['PAYPAL'] = $this->createStub(PaymentMethodInterface::class);

        $gatewayConfig->expects(self::once())->method('setConfig')->with(array_merge(self::CONFIG, ['sandbox' => true]));
        $paymentMethod->expects(self::once())->method('setCode')->with('PAYPAL_2');
        $this->entityManager->expects(self::once())->method('persist')->with($paymentMethod);

        $result = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true));

        self::assertSame($paymentMethod, $this->creator->create($result, true));
    }

    #[Test]
    public function it_creates_the_payment_method_of_another_paypal_account_with_the_first_free_code_and_a_matching_name(): void
    {
        [, $paymentMethod] = $this->mockFactories();
        $this->paymentMethods['PAYPAL'] = $this->createStub(PaymentMethodInterface::class);
        $this->paymentMethods['PAYPAL_2'] = $this->createStub(PaymentMethodInterface::class);

        $paymentMethod->expects(self::once())->method('setCode')->with('PAYPAL_3');
        $paymentMethod->expects(self::once())->method('setName')->with('PayPal 3');
        $this->entityManager->expects(self::once())->method('persist')->with($paymentMethod);

        $result = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true));

        self::assertSame($paymentMethod, $this->creator->create($result, false));
    }

    #[Test]
    public function it_takes_the_gateway_config_off_payum_when_the_store_still_ships_payum(): void
    {
        $gatewayConfig = new class() extends GatewayConfig {
            public bool $usePayum = true;

            public function setUsePayum(bool $usePayum): void
            {
                $this->usePayum = $usePayum;
            }
        };
        $this->gatewayFactory->method('createNew')->willReturn($gatewayConfig);
        $this->paymentMethodFactory->method('createNew')->willReturn($this->createMock(PaymentMethodInterface::class));

        $this->creator->create(new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true)), false);

        self::assertFalse($gatewayConfig->usePayum);
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
