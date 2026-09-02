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
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Manager\PayPalCredentialsManager;
use Sylius\PayPalPlugin\Onboarding\Manager\SellerNonceManagerInterface;
use Sylius\PayPalPlugin\Provider\PayPalOnboardingUrlProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;
use Sylius\PayPalPlugin\Twig\Component\PayPalOnboardingModalComponent;

final class PayPalOnboardingModalComponentTest extends TestCase
{
    private PayPalOnboardingUrlProviderInterface&MockObject $onboardingUrlProvider;

    private SellerNonceManagerInterface&MockObject $sellerNonceManager;

    private PayPalPaymentMethodProviderInterface&MockObject $payPalPaymentMethodProvider;

    private LoggerInterface&MockObject $logger;

    private PayPalOnboardingModalComponent $payPalOnboardingModalComponent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->onboardingUrlProvider = $this->createMock(PayPalOnboardingUrlProviderInterface::class);
        $this->sellerNonceManager = $this->createMock(SellerNonceManagerInterface::class);
        $this->payPalPaymentMethodProvider = $this->createMock(PayPalPaymentMethodProviderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->payPalOnboardingModalComponent = new PayPalOnboardingModalComponent(
            $this->onboardingUrlProvider,
            $this->sellerNonceManager,
            $this->payPalPaymentMethodProvider,
            $this->logger,
            new PayPalCredentialsManager(),
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
        $this->mockExistingPaymentMethod(['production_credentials' => ['client_id' => 'PROD-CLIENT-ID']]);

        $this->sellerNonceManager->expects(self::never())->method('generate');
        $this->onboardingUrlProvider->expects(self::never())->method('generate');

        $this->payPalOnboardingModalComponent->loadOnboardingUrl();

        self::assertSame('', $this->payPalOnboardingModalComponent->onboardingUrl);
        self::assertFalse($this->payPalOnboardingModalComponent->loading);
        self::assertFalse($this->payPalOnboardingModalComponent->failed);
        self::assertTrue($this->payPalOnboardingModalComponent->sellerAlreadyOnboarded);
    }

    #[Test]
    public function it_loads_the_onboarding_url_when_only_sandbox_credentials_exist(): void
    {
        $this->mockExistingPaymentMethod(['sandbox' => true, 'sandbox_credentials' => ['client_id' => 'SANDBOX-CLIENT-ID']]);
        $this->sellerNonceManager->method('generate')->willReturn('SELLER-NONCE');
        $this->onboardingUrlProvider->method('generate')->with('SELLER-NONCE')->willReturn('https://www.paypal.com/onboarding');

        $this->payPalOnboardingModalComponent->loadOnboardingUrl();

        self::assertSame('https://www.paypal.com/onboarding', $this->payPalOnboardingModalComponent->onboardingUrl);
        self::assertFalse($this->payPalOnboardingModalComponent->sellerAlreadyOnboarded);
        self::assertTrue($this->payPalOnboardingModalComponent->opened);
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
