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
use Sylius\PayPalPlugin\Onboarding\Checker\SellerOnboardedCheckerInterface;
use Sylius\PayPalPlugin\Onboarding\Manager\SellerNonceManagerInterface;
use Sylius\PayPalPlugin\Provider\PayPalOnboardingUrlProviderInterface;
use Sylius\PayPalPlugin\Twig\Component\PayPalOnboardingModalComponent;

final class PayPalOnboardingModalComponentTest extends TestCase
{
    private PayPalOnboardingUrlProviderInterface&MockObject $onboardingUrlProvider;

    private SellerNonceManagerInterface&MockObject $sellerNonceManager;

    private SellerOnboardedCheckerInterface&MockObject $sellerOnboardedChecker;

    private LoggerInterface&MockObject $logger;

    private PayPalOnboardingModalComponent $payPalOnboardingModalComponent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->onboardingUrlProvider = $this->createMock(PayPalOnboardingUrlProviderInterface::class);
        $this->sellerNonceManager = $this->createMock(SellerNonceManagerInterface::class);
        $this->sellerOnboardedChecker = $this->createMock(SellerOnboardedCheckerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->payPalOnboardingModalComponent = new PayPalOnboardingModalComponent(
            $this->onboardingUrlProvider,
            $this->sellerNonceManager,
            $this->sellerOnboardedChecker,
            $this->logger,
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
    public function it_does_not_load_the_onboarding_url_when_a_sandbox_seller_is_already_onboarded_in_sandbox_mode(): void
    {
        $this->payPalOnboardingModalComponent->sandbox = true;
        $this->sellerOnboardedChecker->method('isOnboarded')->with(true)->willReturn(true);

        $this->sellerNonceManager->expects(self::never())->method('generate');
        $this->onboardingUrlProvider->expects(self::never())->method('generate');

        $this->payPalOnboardingModalComponent->loadOnboardingUrl();

        self::assertTrue($this->payPalOnboardingModalComponent->sellerAlreadyOnboarded);
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
    public function it_does_not_load_the_onboarding_url_when_a_production_seller_is_already_onboarded(): void
    {
        $this->sellerOnboardedChecker->method('isOnboarded')->with(false)->willReturn(true);

        $this->sellerNonceManager->expects(self::never())->method('generate');
        $this->onboardingUrlProvider->expects(self::never())->method('generate');

        $this->payPalOnboardingModalComponent->loadOnboardingUrl();

        self::assertSame('', $this->payPalOnboardingModalComponent->onboardingUrl);
        self::assertFalse($this->payPalOnboardingModalComponent->loading);
        self::assertFalse($this->payPalOnboardingModalComponent->failed);
        self::assertTrue($this->payPalOnboardingModalComponent->sellerAlreadyOnboarded);
    }

    #[Test]
    public function it_loads_the_onboarding_url_when_no_production_seller_is_onboarded(): void
    {
        $this->sellerOnboardedChecker->method('isOnboarded')->willReturn(false);
        $this->sellerNonceManager->method('generate')->willReturn('SELLER-NONCE');
        $this->onboardingUrlProvider->method('generate')->with('SELLER-NONCE', false)->willReturn('https://www.paypal.com/onboarding');

        $this->payPalOnboardingModalComponent->loadOnboardingUrl();

        self::assertSame('https://www.paypal.com/onboarding', $this->payPalOnboardingModalComponent->onboardingUrl);
        self::assertFalse($this->payPalOnboardingModalComponent->sellerAlreadyOnboarded);
        self::assertTrue($this->payPalOnboardingModalComponent->opened);
    }
}
