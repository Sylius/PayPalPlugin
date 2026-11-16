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

namespace Tests\Sylius\PayPalPlugin\Unit\Twig\Component;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\Exception\MerchantIntegrationStatusUnavailableException;
use Sylius\PayPalPlugin\Model\Capability;
use Sylius\PayPalPlugin\Model\PermissionsVerification;
use Sylius\PayPalPlugin\Onboarding\Manager\SellerNonceManagerInterface;
use Sylius\PayPalPlugin\Provider\PayPalOnboardingUrlProviderInterface;
use Sylius\PayPalPlugin\Twig\Component\PayPalOnboardingModalComponent;
use Sylius\PayPalPlugin\Verifier\PermissionsVerifierInterface;

final class PayPalOnboardingModalComponentTest extends TestCase
{
    private PayPalOnboardingUrlProviderInterface&MockObject $onboardingUrlProvider;

    private SellerNonceManagerInterface&MockObject $sellerNonceManager;

    private LoggerInterface&MockObject $logger;

    /** @var PaymentMethodRepositoryInterface<PaymentMethodInterface>&MockObject */
    private PaymentMethodRepositoryInterface&MockObject $paymentMethodRepository;

    private PermissionsVerifierInterface&MockObject $permissionsVerifier;

    private PayPalOnboardingModalComponent $payPalOnboardingModalComponent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->onboardingUrlProvider = $this->createMock(PayPalOnboardingUrlProviderInterface::class);
        $this->sellerNonceManager = $this->createMock(SellerNonceManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $this->permissionsVerifier = $this->createMock(PermissionsVerifierInterface::class);

        $this->payPalOnboardingModalComponent = new PayPalOnboardingModalComponent(
            $this->onboardingUrlProvider,
            $this->sellerNonceManager,
            $this->logger,
            $this->paymentMethodRepository,
            $this->permissionsVerifier,
        );
    }

    #[Test]
    public function it_starts_in_a_loading_state_without_calling_any_dependency(): void
    {
        $this->onboardingUrlProvider->expects(self::never())->method('generate');
        $this->sellerNonceManager->expects(self::never())->method('generate');

        self::assertTrue($this->payPalOnboardingModalComponent->loading);
        self::assertFalse($this->payPalOnboardingModalComponent->failed);
        self::assertSame('', $this->payPalOnboardingModalComponent->onboardingUrl);
    }

    #[Test]
    public function it_loads_the_onboarding_url_when_the_action_is_triggered(): void
    {
        $this->sellerNonceManager->method('generate')->willReturn('NONCE');
        $this->onboardingUrlProvider
            ->expects(self::once())
            ->method('generate')
            ->with('NONCE', false)
            ->willReturn('https://www.sandbox.paypal.com/bizsignup/partner/entry?sellerNonce=NONCE');

        $this->payPalOnboardingModalComponent->loadOnboardingUrl();

        self::assertSame(
            'https://www.sandbox.paypal.com/bizsignup/partner/entry?sellerNonce=NONCE',
            $this->payPalOnboardingModalComponent->onboardingUrl,
        );
        self::assertFalse($this->payPalOnboardingModalComponent->loading);
        self::assertFalse($this->payPalOnboardingModalComponent->failed);
    }

    #[Test]
    public function it_starts_a_sandbox_onboarding_session_in_sandbox_mode(): void
    {
        $this->payPalOnboardingModalComponent->sandbox = true;

        $this->sellerNonceManager->expects(self::once())->method('generate')->with(true)->willReturn('NONCE');
        $this->onboardingUrlProvider
            ->expects(self::once())
            ->method('generate')
            ->with('NONCE', true)
            ->willReturn('https://www.sandbox.paypal.com/bizsignup/partner/entry?sellerNonce=NONCE');

        $this->payPalOnboardingModalComponent->loadOnboardingUrl();

        self::assertSame(
            'https://www.sandbox.paypal.com/bizsignup/partner/entry?sellerNonce=NONCE',
            $this->payPalOnboardingModalComponent->onboardingUrl,
        );
    }

    #[Test]
    public function it_marks_the_component_as_failed_and_logs_when_the_url_provider_throws(): void
    {
        $this->sellerNonceManager->method('generate')->willReturn('NONCE');
        $this->onboardingUrlProvider->method('generate')->willThrowException(new \RuntimeException('endpoint unreachable'));

        $this->logger->expects(self::once())->method('error');

        $this->payPalOnboardingModalComponent->loadOnboardingUrl();

        self::assertSame('', $this->payPalOnboardingModalComponent->onboardingUrl);
        self::assertFalse($this->payPalOnboardingModalComponent->loading);
        self::assertTrue($this->payPalOnboardingModalComponent->failed);
    }

    #[Test]
    public function it_marks_the_modal_as_opened_once_it_loads_the_onboarding_url(): void
    {
        $this->sellerNonceManager->method('generate')->willReturn('SELLER-NONCE');
        $this->onboardingUrlProvider->method('generate')->with('SELLER-NONCE', false)->willReturn('https://www.paypal.com/onboarding');

        $this->payPalOnboardingModalComponent->loadOnboardingUrl();

        self::assertSame('https://www.paypal.com/onboarding', $this->payPalOnboardingModalComponent->onboardingUrl);
        self::assertTrue($this->payPalOnboardingModalComponent->opened);
    }

    #[Test]
    public function it_does_not_verify_the_permissions_when_it_connects_a_new_paypal_account(): void
    {
        $this->sellerNonceManager->method('generate')->willReturn('NONCE');
        $this->onboardingUrlProvider->method('generate')->willReturn('https://www.paypal.com/onboarding');

        $this->permissionsVerifier->expects(self::never())->method('verify');

        $this->payPalOnboardingModalComponent->loadOnboardingUrl();

        self::assertSame('https://www.paypal.com/onboarding', $this->payPalOnboardingModalComponent->onboardingUrl);
    }

    #[Test]
    public function it_offers_the_consent_when_the_permissions_of_the_payment_method_need_it(): void
    {
        $this->payPalOnboardingModalComponent->paymentMethodId = 1;
        $paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $this->paymentMethodRepository->method('find')->with(1)->willReturn($paymentMethod);
        $this->permissionsVerifier->method('verify')->with($paymentMethod)->willReturn(PermissionsVerification::consentRequired());
        $this->sellerNonceManager->method('generate')->willReturn('NONCE');
        $this->onboardingUrlProvider->method('generate')->willReturn('https://www.paypal.com/onboarding');

        $this->payPalOnboardingModalComponent->loadOnboardingUrl();

        self::assertSame('https://www.paypal.com/onboarding', $this->payPalOnboardingModalComponent->onboardingUrl);
        self::assertFalse($this->payPalOnboardingModalComponent->loading);
    }

    #[Test]
    public function it_offers_no_consent_when_the_permissions_are_up_to_date_or_await_paypal(): void
    {
        $this->payPalOnboardingModalComponent->paymentMethodId = 1;
        $this->paymentMethodRepository->method('find')->willReturn($this->createStub(PaymentMethodInterface::class));
        $this->permissionsVerifier->method('verify')->willReturnOnConsecutiveCalls(
            PermissionsVerification::upToDate(),
            PermissionsVerification::awaitingPayPal([Capability::GOOGLE_PAY => 'NEED_DATA']),
        );

        $this->sellerNonceManager->expects(self::never())->method('generate');
        $this->onboardingUrlProvider->expects(self::never())->method('generate');

        $this->payPalOnboardingModalComponent->loadOnboardingUrl();
        self::assertTrue($this->payPalOnboardingModalComponent->verification?->isUpToDate());

        $this->payPalOnboardingModalComponent->loadOnboardingUrl();
        self::assertTrue($this->payPalOnboardingModalComponent->verification?->isAwaitingPayPal());

        self::assertSame('', $this->payPalOnboardingModalComponent->onboardingUrl);
        self::assertFalse($this->payPalOnboardingModalComponent->loading);
    }

    #[Test]
    public function it_still_offers_the_consent_when_the_permissions_cannot_be_verified(): void
    {
        $this->payPalOnboardingModalComponent->paymentMethodId = 1;
        $this->paymentMethodRepository->method('find')->willReturn($this->createStub(PaymentMethodInterface::class));
        $this->permissionsVerifier->method('verify')->willThrowException(
            new MerchantIntegrationStatusUnavailableException('PAYPAL', new \RuntimeException('PayPal is down')),
        );
        $this->sellerNonceManager->method('generate')->willReturn('NONCE');
        $this->onboardingUrlProvider->method('generate')->willReturn('https://www.paypal.com/onboarding');

        $this->logger->expects(self::once())->method('warning')->with(self::stringContains('PayPal is down'));

        $this->payPalOnboardingModalComponent->loadOnboardingUrl();

        self::assertTrue($this->payPalOnboardingModalComponent->verificationFailed);
        self::assertSame('https://www.paypal.com/onboarding', $this->payPalOnboardingModalComponent->onboardingUrl);
    }
}
