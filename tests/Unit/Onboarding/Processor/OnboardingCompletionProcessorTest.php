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
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Creator\PayPalOnboardingPaymentMethodCreatorInterface;
use Sylius\PayPalPlugin\Exception\OnboardingFailedException;
use Sylius\PayPalPlugin\Exception\OnboardingSessionExpiredException;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookAlreadyRegisteredException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookUrlNotValidException;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\SellerOnboardingResult;
use Sylius\PayPalPlugin\Onboarding\Manager\SellerNonceManagerInterface;
use Sylius\PayPalPlugin\Onboarding\Processor\OnboardingCompletionProcessor;
use Sylius\PayPalPlugin\Onboarding\Resolver\SellerOnboardingResolverInterface;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;

final class OnboardingCompletionProcessorTest extends TestCase
{
    private SellerNonceManagerInterface&MockObject $sellerNonceManager;

    private SellerOnboardingResolverInterface&MockObject $sellerOnboardingResolver;

    private PayPalOnboardingPaymentMethodCreatorInterface&MockObject $onboardingPaymentMethodCreator;

    private SellerWebhookRegistrarInterface&MockObject $sellerWebhookRegistrar;

    private EntityManagerInterface&MockObject $entityManager;

    private OnboardingCompletionProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sellerNonceManager = $this->createMock(SellerNonceManagerInterface::class);
        $this->sellerOnboardingResolver = $this->createMock(SellerOnboardingResolverInterface::class);
        $this->onboardingPaymentMethodCreator = $this->createMock(PayPalOnboardingPaymentMethodCreatorInterface::class);
        $this->sellerWebhookRegistrar = $this->createMock(SellerWebhookRegistrarInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->processor = new OnboardingCompletionProcessor(
            $this->sellerNonceManager,
            $this->sellerOnboardingResolver,
            $this->onboardingPaymentMethodCreator,
            $this->sellerWebhookRegistrar,
            $this->entityManager,
        );
    }

    #[Test]
    public function it_completes_the_onboarding(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $status = new OnboardingStatus(true, true);
        $sellerOnboardingResult = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', $status);

        $this->sellerNonceManager->method('get')->willReturn('SELLER-NONCE');
        $this->sellerOnboardingResolver
            ->expects(self::once())
            ->method('resolve')
            ->with('AUTH-CODE', 'SHARED-ID', 'SELLER-NONCE', false)
            ->willReturn($sellerOnboardingResult);
        $this->onboardingPaymentMethodCreator->method('create')->with($sellerOnboardingResult, false)->willReturn($paymentMethod);
        $this->sellerWebhookRegistrar->expects(self::once())->method('register')->with($paymentMethod);
        $paymentMethod->expects(self::never())->method('setEnabled');
        $this->entityManager->expects(self::once())->method('flush');
        $this->sellerNonceManager->expects(self::once())->method('remove')->with(false);

        $result = $this->processor->process('AUTH-CODE', 'SHARED-ID', false);

        self::assertSame($paymentMethod, $result->getPaymentMethod());
        self::assertSame($status, $result->getStatus());
        self::assertTrue($result->isWebhookUrlValid());
        self::assertFalse($result->isSandbox());
    }

    #[Test]
    public function it_completes_the_sandbox_onboarding_into_the_sandbox_vault(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $status = new OnboardingStatus(true, true);
        $sellerOnboardingResult = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', $status);

        $this->sellerNonceManager->method('get')->willReturn('SELLER-NONCE');
        $this->sellerOnboardingResolver
            ->expects(self::once())
            ->method('resolve')
            ->with('AUTH-CODE', 'SHARED-ID', 'SELLER-NONCE', true)
            ->willReturn($sellerOnboardingResult);
        $this->onboardingPaymentMethodCreator
            ->expects(self::once())
            ->method('create')
            ->with($sellerOnboardingResult, true)
            ->willReturn($paymentMethod);
        $this->sellerWebhookRegistrar->expects(self::once())->method('register')->with($paymentMethod);

        $result = $this->processor->process('AUTH-CODE', 'SHARED-ID', true);

        self::assertSame($paymentMethod, $result->getPaymentMethod());
        self::assertTrue($result->isSandbox());
    }

    #[Test]
    public function it_completes_the_onboarding_into_the_payment_method_it_was_started_for(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $sellerOnboardingResult = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true));

        $this->sellerNonceManager->method('get')->willReturn('SELLER-NONCE');
        $this->sellerOnboardingResolver->method('resolve')->willReturn($sellerOnboardingResult);
        $this->onboardingPaymentMethodCreator
            ->expects(self::once())
            ->method('create')
            ->with($sellerOnboardingResult, true, 'PAYPAL_2')
            ->willReturn($paymentMethod);

        $result = $this->processor->process('AUTH-CODE', 'SHARED-ID', true, 'PAYPAL_2');

        self::assertSame($paymentMethod, $result->getPaymentMethod());
    }

    #[Test]
    public function it_disables_the_payment_method_when_the_webhook_url_is_not_valid(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $this->mockSuccessfulResolution($paymentMethod);
        $this->sellerWebhookRegistrar->method('register')->willThrowException(new PayPalWebhookUrlNotValidException());
        $paymentMethod->expects(self::once())->method('setEnabled')->with(false);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->processor->process('AUTH-CODE', 'SHARED-ID', false);

        self::assertFalse($result->isWebhookUrlValid());
    }

    #[Test]
    public function it_treats_an_already_registered_webhook_as_valid(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $this->mockSuccessfulResolution($paymentMethod);
        $this->sellerWebhookRegistrar->method('register')->willThrowException(new PayPalWebhookAlreadyRegisteredException());
        $paymentMethod->expects(self::never())->method('setEnabled');

        $result = $this->processor->process('AUTH-CODE', 'SHARED-ID', false);

        self::assertTrue($result->isWebhookUrlValid());
    }

    #[Test]
    public function it_throws_an_exception_when_the_seller_nonce_is_missing(): void
    {
        $this->sellerNonceManager->method('get')->willReturn(null);

        $this->sellerOnboardingResolver->expects(self::never())->method('resolve');

        $this->expectException(OnboardingSessionExpiredException::class);

        $this->processor->process('AUTH-CODE', 'SHARED-ID', false);
    }

    #[Test]
    public function it_wraps_failures_and_keeps_the_seller_nonce(): void
    {
        $this->sellerNonceManager->method('get')->willReturn('SELLER-NONCE');
        $this->sellerOnboardingResolver->method('resolve')->willThrowException(new PayPalPluginException('boom'));

        $this->entityManager->expects(self::never())->method('flush');
        $this->sellerNonceManager->expects(self::never())->method('remove')->with(false);

        $this->expectException(OnboardingFailedException::class);

        $this->processor->process('AUTH-CODE', 'SHARED-ID', false);
    }

    #[Test]
    public function it_does_not_flush_the_payment_method_when_the_webhook_registration_fails(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $this->mockSuccessfulResolution($paymentMethod);
        $this->sellerWebhookRegistrar->method('register')->willThrowException(new PayPalPluginException('boom'));

        $this->entityManager->expects(self::never())->method('flush');
        $this->sellerNonceManager->expects(self::never())->method('remove')->with(false);

        $this->expectException(OnboardingFailedException::class);

        $this->processor->process('AUTH-CODE', 'SHARED-ID', false);
    }

    private function mockSuccessfulResolution(PaymentMethodInterface $paymentMethod): void
    {
        $this->sellerNonceManager->method('get')->willReturn('SELLER-NONCE');
        $this->sellerOnboardingResolver->method('resolve')->willReturn(
            new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true)),
        );
        $this->onboardingPaymentMethodCreator->method('create')->willReturn($paymentMethod);
    }
}
