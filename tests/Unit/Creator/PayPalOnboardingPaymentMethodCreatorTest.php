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
use Sylius\PayPalPlugin\Manager\PayPalCredentialsManagerInterface;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\SellerOnboardingResult;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;

final class PayPalOnboardingPaymentMethodCreatorTest extends TestCase
{
    private const STORED_CONFIG = [
        'client_id' => 'CLIENT-ID',
        'client_secret' => 'CLIENT-SECRET',
        'merchant_id' => 'MERCHANT-ID',
        'use_authorize' => 1,
        'sylius_merchant_id' => 'MERCHANT-ID',
        'reports_sftp_password' => null,
        'reports_sftp_username' => null,
        'partner_attribution_id' => 'sylius-ppcp4p-bn-code',
    ];

    private SellerWebhookRegistrarInterface&MockObject $sellerWebhookRegistrar;

    private FactoryInterface&MockObject $gatewayFactory;

    private FactoryInterface&MockObject $paymentMethodFactory;

    private EntityManagerInterface&MockObject $entityManager;

    private PayPalPaymentMethodProviderInterface&MockObject $payPalPaymentMethodProvider;

    private PayPalCredentialsManagerInterface&MockObject $credentialsManager;

    private PayPalOnboardingPaymentMethodCreator $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sellerWebhookRegistrar = $this->createMock(SellerWebhookRegistrarInterface::class);
        $this->gatewayFactory = $this->createMock(FactoryInterface::class);
        $this->paymentMethodFactory = $this->createMock(FactoryInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->payPalPaymentMethodProvider = $this->createMock(PayPalPaymentMethodProviderInterface::class);
        $this->credentialsManager = $this->createMock(PayPalCredentialsManagerInterface::class);

        $this->payPalPaymentMethodProvider->method('exists')->willReturn(false);
        $this->credentialsManager->method('store')->willReturn(self::STORED_CONFIG);

        $this->creator = new PayPalOnboardingPaymentMethodCreator(
            $this->sellerWebhookRegistrar,
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
        [$gatewayConfig, $paymentMethod, $enabledStates] = $this->mockFactories();

        $result = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true));

        $gatewayConfig->expects(self::once())->method('setFactoryName')->with('sylius_paypal');
        $gatewayConfig->expects(self::once())->method('setGatewayName')->with('sylius_paypal');
        $gatewayConfig->expects(self::once())->method('setConfig')->with(self::STORED_CONFIG);

        $this->sellerWebhookRegistrar->expects(self::once())->method('register')->with($paymentMethod);
        $this->entityManager->expects(self::once())->method('persist')->with($paymentMethod);
        $this->entityManager->expects(self::exactly(2))->method('flush');

        $resultPaymentMethod = $this->creator->create($result);

        self::assertSame($paymentMethod, $resultPaymentMethod);
        self::assertSame([true], $enabledStates());
    }

    #[Test]
    public function it_disables_the_payment_method_when_onboarding_status_is_incomplete(): void
    {
        [, $paymentMethod, $enabledStates] = $this->mockFactories();

        $result = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(false, true));

        $this->sellerWebhookRegistrar->expects(self::once())->method('register')->with($paymentMethod);

        $resultPaymentMethod = $this->creator->create($result);

        self::assertSame($paymentMethod, $resultPaymentMethod);
        self::assertSame([true, false], $enabledStates());
    }

    #[Test]
    public function it_disables_the_payment_method_when_webhook_url_is_not_valid(): void
    {
        [, $paymentMethod, $enabledStates] = $this->mockFactories();

        $result = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true));

        $this->sellerWebhookRegistrar
            ->method('register')
            ->willThrowException(new PayPalWebhookUrlNotValidException());

        $resultPaymentMethod = $this->creator->create($result);

        self::assertSame($paymentMethod, $resultPaymentMethod);
        self::assertSame([true, false], $enabledStates());
    }

    #[Test]
    public function it_disables_the_payment_method_and_rethrows_on_an_unexpected_webhook_failure(): void
    {
        [, $paymentMethod, $enabledStates] = $this->mockFactories();

        $result = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true));

        $exception = new \RuntimeException('PayPal API timeout');
        $this->sellerWebhookRegistrar->method('register')->willThrowException($exception);
        $this->entityManager->expects(self::exactly(2))->method('flush');

        try {
            $this->creator->create($result);
            self::fail('Expected the unexpected webhook failure to be rethrown.');
        } catch (\RuntimeException $caught) {
            self::assertSame($exception, $caught);
        }

        self::assertSame([true, false], $enabledStates());
    }

    /**
     * @return array{0: GatewayConfigInterface&MockObject, 1: PaymentMethodInterface&MockObject, 2: callable(): list<bool>}
     */
    private function mockFactories(): array
    {
        /** @var GatewayConfigInterface&MockObject $gatewayConfig */
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        /** @var PaymentMethodInterface&MockObject $paymentMethod */
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $this->gatewayFactory->method('createNew')->willReturn($gatewayConfig);
        $this->paymentMethodFactory->method('createNew')->willReturn($paymentMethod);
        $paymentMethod->method('setGatewayConfig');

        $enabledStates = [];
        $paymentMethod->method('setEnabled')->willReturnCallback(
            function (bool $enabled) use (&$enabledStates): void {
                $enabledStates[] = $enabled;
            },
        );

        return [$gatewayConfig, $paymentMethod, static function () use (&$enabledStates): array {
            return $enabledStates;
        }];
    }
}
