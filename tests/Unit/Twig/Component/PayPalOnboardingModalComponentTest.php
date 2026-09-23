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
use Sylius\PayPalPlugin\Provider\PayPalOnboardingUrlProviderInterface;
use Sylius\PayPalPlugin\Provider\SellerNonceProviderInterface;
use Sylius\PayPalPlugin\Twig\Component\PayPalOnboardingModalComponent;

final class PayPalOnboardingModalComponentTest extends TestCase
{
    private PayPalOnboardingUrlProviderInterface&MockObject $onboardingUrlProvider;

    private SellerNonceProviderInterface&MockObject $sellerNonceProvider;

    private LoggerInterface&MockObject $logger;

    private PayPalOnboardingModalComponent $payPalOnboardingModalComponent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->onboardingUrlProvider = $this->createMock(PayPalOnboardingUrlProviderInterface::class);
        $this->sellerNonceProvider = $this->createMock(SellerNonceProviderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->payPalOnboardingModalComponent = new PayPalOnboardingModalComponent(
            $this->onboardingUrlProvider,
            $this->sellerNonceProvider,
            $this->logger,
        );
    }

    #[Test]
    public function it_starts_in_a_loading_state_without_calling_any_dependency(): void
    {
        $this->onboardingUrlProvider->expects(self::never())->method('generate');
        $this->sellerNonceProvider->expects(self::never())->method('generate');

        self::assertTrue($this->payPalOnboardingModalComponent->loading);
        self::assertFalse($this->payPalOnboardingModalComponent->failed);
        self::assertSame('', $this->payPalOnboardingModalComponent->onboardingUrl);
    }

    #[Test]
    public function it_loads_the_onboarding_url_when_the_action_is_triggered(): void
    {
        $this->sellerNonceProvider->method('generate')->willReturn('NONCE');
        $this->onboardingUrlProvider
            ->expects(self::once())
            ->method('generate')
            ->with('NONCE')
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
    public function it_marks_the_component_as_failed_and_logs_when_the_url_provider_throws(): void
    {
        $this->sellerNonceProvider->method('generate')->willReturn('NONCE');
        $this->onboardingUrlProvider->method('generate')->willThrowException(new \RuntimeException('endpoint unreachable'));

        $this->logger->expects(self::once())->method('error');

        $this->payPalOnboardingModalComponent->loadOnboardingUrl();

        self::assertSame('', $this->payPalOnboardingModalComponent->onboardingUrl);
        self::assertFalse($this->payPalOnboardingModalComponent->loading);
        self::assertTrue($this->payPalOnboardingModalComponent->failed);
    }
}
