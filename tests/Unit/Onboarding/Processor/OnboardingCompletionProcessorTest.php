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

namespace Tests\Sylius\PayPalPlugin\Unit\Onboarding\Processor;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Creator\PayPalOnboardingPaymentMethodCreatorInterface;
use Sylius\PayPalPlugin\Exception\OnboardingFailedException;
use Sylius\PayPalPlugin\Exception\OnboardingSessionExpiredException;
use Sylius\PayPalPlugin\Exception\PayPalPaymentMethodAlreadyExistsException;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookAlreadyRegisteredException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookUrlNotValidException;
use Sylius\PayPalPlugin\Manager\PayPalCredentialsManager;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\SellerOnboardingResult;
use Sylius\PayPalPlugin\Onboarding\Manager\SellerNonceManagerInterface;
use Sylius\PayPalPlugin\Onboarding\Processor\OnboardingCompletionProcessor;
use Sylius\PayPalPlugin\Onboarding\Resolver\SellerOnboardingResolverInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;

final class OnboardingCompletionProcessorTest extends TestCase
{
    private PayPalPaymentMethodProviderInterface&MockObject $payPalPaymentMethodProvider;

    private SellerNonceManagerInterface&MockObject $sellerNonceManager;

    private SellerOnboardingResolverInterface&MockObject $sellerOnboardingResolver;

    private PayPalOnboardingPaymentMethodCreatorInterface&MockObject $onboardingPaymentMethodCreator;

    private SellerWebhookRegistrarInterface&MockObject $sellerWebhookRegistrar;

    private EntityManagerInterface&MockObject $entityManager;

    private OnboardingCompletionProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payPalPaymentMethodProvider = $this->createMock(PayPalPaymentMethodProviderInterface::class);
        $this->sellerNonceManager = $this->createMock(SellerNonceManagerInterface::class);
        $this->sellerOnboardingResolver = $this->createMock(SellerOnboardingResolverInterface::class);
        $this->onboardingPaymentMethodCreator = $this->createMock(PayPalOnboardingPaymentMethodCreatorInterface::class);
        $this->sellerWebhookRegistrar = $this->createMock(SellerWebhookRegistrarInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->processor = new OnboardingCompletionProcessor(
            $this->payPalPaymentMethodProvider,
            $this->sellerNonceManager,
            $this->sellerOnboardingResolver,
            $this->onboardingPaymentMethodCreator,
            $this->sellerWebhookRegistrar,
            $this->entityManager,
            new PayPalCredentialsManager(),
        );
    }

    #[Test]
    public function it_completes_the_onboarding(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $status = new OnboardingStatus(true, true);
        $sellerOnboardingResult = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', $status);

        $this->payPalPaymentMethodProvider->method('exists')->willReturn(false);
        $this->sellerNonceManager->method('get')->willReturn('SELLER-NONCE');
        $this->sellerOnboardingResolver
            ->expects(self::once())
            ->method('resolve')
            ->with('AUTH-CODE', 'SHARED-ID', 'SELLER-NONCE')
            ->willReturn($sellerOnboardingResult);
        $this->onboardingPaymentMethodCreator->method('create')->with($sellerOnboardingResult)->willReturn($paymentMethod);
        $this->sellerWebhookRegistrar->expects(self::once())->method('register')->with($paymentMethod);
        $paymentMethod->expects(self::never())->method('setEnabled');
        $this->entityManager->expects(self::once())->method('flush');
        $this->sellerNonceManager->expects(self::once())->method('remove');

        $result = $this->processor->process('AUTH-CODE', 'SHARED-ID');

        self::assertSame($paymentMethod, $result->getPaymentMethod());
        self::assertSame($status, $result->getStatus());
        self::assertTrue($result->isWebhookUrlValid());
    }

    #[Test]
    public function it_disables_the_payment_method_when_the_webhook_url_is_not_valid(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $this->mockSuccessfulResolution($paymentMethod);
        $this->sellerWebhookRegistrar->method('register')->willThrowException(new PayPalWebhookUrlNotValidException());
        $paymentMethod->expects(self::once())->method('setEnabled')->with(false);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->processor->process('AUTH-CODE', 'SHARED-ID');

        self::assertFalse($result->isWebhookUrlValid());
    }

    #[Test]
    public function it_treats_an_already_registered_webhook_as_valid(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $this->mockSuccessfulResolution($paymentMethod);
        $this->sellerWebhookRegistrar->method('register')->willThrowException(new PayPalWebhookAlreadyRegisteredException());
        $paymentMethod->expects(self::never())->method('setEnabled');

        $result = $this->processor->process('AUTH-CODE', 'SHARED-ID');

        self::assertTrue($result->isWebhookUrlValid());
    }

    #[Test]
    public function it_throws_an_exception_when_a_production_seller_is_already_onboarded(): void
    {
        $this->mockExistingPaymentMethod(['production_credentials' => ['client_id' => 'PROD-CLIENT-ID']]);

        $this->sellerOnboardingResolver->expects(self::never())->method('resolve');

        $this->expectException(PayPalPaymentMethodAlreadyExistsException::class);

        $this->processor->process('AUTH-CODE', 'SHARED-ID');
    }

    #[Test]
    public function it_onboards_production_into_an_existing_sandbox_only_payment_method(): void
    {
        $this->mockExistingPaymentMethod(['sandbox' => true, 'sandbox_credentials' => ['client_id' => 'SANDBOX-CLIENT-ID']]);
        $this->sellerNonceManager->method('get')->willReturn(null);

        $this->expectException(OnboardingSessionExpiredException::class);

        $this->processor->process('AUTH-CODE', 'SHARED-ID');
    }

    #[Test]
    public function it_throws_an_exception_when_the_seller_nonce_is_missing(): void
    {
        $this->payPalPaymentMethodProvider->method('exists')->willReturn(false);
        $this->sellerNonceManager->method('get')->willReturn(null);

        $this->sellerOnboardingResolver->expects(self::never())->method('resolve');

        $this->expectException(OnboardingSessionExpiredException::class);

        $this->processor->process('AUTH-CODE', 'SHARED-ID');
    }

    #[Test]
    public function it_wraps_failures_and_keeps_the_seller_nonce(): void
    {
        $this->payPalPaymentMethodProvider->method('exists')->willReturn(false);
        $this->sellerNonceManager->method('get')->willReturn('SELLER-NONCE');
        $this->sellerOnboardingResolver->method('resolve')->willThrowException(new PayPalPluginException('boom'));

        $this->entityManager->expects(self::never())->method('flush');
        $this->sellerNonceManager->expects(self::never())->method('remove');

        $this->expectException(OnboardingFailedException::class);

        $this->processor->process('AUTH-CODE', 'SHARED-ID');
    }

    private function mockSuccessfulResolution(PaymentMethodInterface $paymentMethod): void
    {
        $this->payPalPaymentMethodProvider->method('exists')->willReturn(false);
        $this->sellerNonceManager->method('get')->willReturn('SELLER-NONCE');
        $this->sellerOnboardingResolver->method('resolve')->willReturn(
            new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true)),
        );
        $this->onboardingPaymentMethodCreator->method('create')->willReturn($paymentMethod);
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
