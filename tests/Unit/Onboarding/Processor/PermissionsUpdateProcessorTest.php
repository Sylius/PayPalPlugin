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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Exception\OnboardingAccountMismatchException;
use Sylius\PayPalPlugin\Exception\OnboardingFailedException;
use Sylius\PayPalPlugin\Exception\OnboardingSessionExpiredException;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\SellerOnboardingResult;
use Sylius\PayPalPlugin\Onboarding\Manager\SellerNonceManagerInterface;
use Sylius\PayPalPlugin\Onboarding\Processor\PermissionsUpdateProcessor;
use Sylius\PayPalPlugin\Onboarding\Processor\PermissionsUpdateProcessorInterface;
use Sylius\PayPalPlugin\Onboarding\Resolver\SellerOnboardingResolverInterface;
use Sylius\PayPalPlugin\Updater\OnboardingScopeUpdaterInterface;

final class PermissionsUpdateProcessorTest extends TestCase
{
    private const CONFIG = [
        'client_id' => 'CLIENT_ID',
        'client_secret' => 'CLIENT_SECRET',
        'merchant_id' => 'MERCHANT_ID',
        'sandbox' => true,
        'webhook_id' => 'WEBHOOK_ID',
    ];

    private SellerNonceManagerInterface&MockObject $sellerNonceManager;

    private SellerOnboardingResolverInterface&MockObject $sellerOnboardingResolver;

    private OnboardingScopeUpdaterInterface&MockObject $onboardingScopeUpdater;

    private PermissionsUpdateProcessor $processor;

    protected function setUp(): void
    {
        $this->sellerNonceManager = $this->createMock(SellerNonceManagerInterface::class);
        $this->sellerOnboardingResolver = $this->createMock(SellerOnboardingResolverInterface::class);
        $this->onboardingScopeUpdater = $this->createMock(OnboardingScopeUpdaterInterface::class);

        $this->processor = new PermissionsUpdateProcessor(
            $this->sellerNonceManager,
            $this->sellerOnboardingResolver,
            $this->onboardingScopeUpdater,
        );
    }

    public function test_it_implements_the_permissions_update_processor_interface(): void
    {
        self::assertInstanceOf(PermissionsUpdateProcessorInterface::class, $this->processor);
    }

    public function test_it_keeps_the_stored_credentials_and_updates_the_onboarding_scope_of_the_same_paypal_account(): void
    {
        $paymentMethod = $this->paymentMethod(self::CONFIG);
        $status = new OnboardingStatus(true, true, ['APPLE_PAY' => 'ACTIVE', 'GOOGLE_PAY' => 'ACTIVE']);
        $this->sellerNonceManager->method('get')->with(true)->willReturn('NONCE');
        $this->sellerOnboardingResolver
            ->expects(self::once())
            ->method('resolve')
            ->with('AUTH_CODE', 'SHARED_ID', 'NONCE', true)
            ->willReturn(new SellerOnboardingResult('NEW_CLIENT_ID', 'NEW_CLIENT_SECRET', 'MERCHANT_ID', $status))
        ;

        $this->onboardingScopeUpdater->expects(self::once())->method('update')->with($paymentMethod);
        $this->sellerNonceManager->expects(self::once())->method('remove')->with(true);

        self::assertSame($status, $this->processor->process($paymentMethod, 'AUTH_CODE', 'SHARED_ID'));
        self::assertSame(self::CONFIG, $paymentMethod->getGatewayConfig()?->getConfig());
    }

    public function test_it_keeps_the_stored_onboarding_scope_while_the_paypal_account_lacks_a_capability_of_it(): void
    {
        $paymentMethod = $this->paymentMethod(self::CONFIG);
        $status = new OnboardingStatus(true, true, ['APPLE_PAY' => 'ACTIVE']);
        $this->sellerNonceManager->method('get')->willReturn('NONCE');
        $this->sellerOnboardingResolver->method('resolve')->willReturn(new SellerOnboardingResult('NEW_CLIENT_ID', 'NEW_CLIENT_SECRET', 'MERCHANT_ID', $status));

        $this->onboardingScopeUpdater->expects(self::never())->method('update');
        $this->sellerNonceManager->expects(self::once())->method('remove')->with(true);

        self::assertSame($status, $this->processor->process($paymentMethod, 'AUTH_CODE', 'SHARED_ID'));
    }

    public function test_it_keeps_the_stored_onboarding_scope_while_paypal_has_not_activated_a_capability_of_it(): void
    {
        $this->sellerNonceManager->method('get')->willReturn('NONCE');
        $this->sellerOnboardingResolver->method('resolve')->willReturn(new SellerOnboardingResult(
            'NEW_CLIENT_ID',
            'NEW_CLIENT_SECRET',
            'MERCHANT_ID',
            new OnboardingStatus(true, true, ['APPLE_PAY' => 'ACTIVE', 'GOOGLE_PAY' => 'PENDING']),
        ));

        $this->onboardingScopeUpdater->expects(self::never())->method('update');

        $this->processor->process($this->paymentMethod(self::CONFIG), 'AUTH_CODE', 'SHARED_ID');
    }

    public function test_it_refuses_another_paypal_account_and_changes_nothing(): void
    {
        $paymentMethod = $this->paymentMethod(self::CONFIG);
        $this->sellerNonceManager->method('get')->willReturn('NONCE');
        $this->sellerOnboardingResolver->method('resolve')->willReturn($this->sellerOnboardingResult('ANOTHER_MERCHANT_ID'));

        $this->onboardingScopeUpdater->expects(self::never())->method('update');
        $this->sellerNonceManager->expects(self::never())->method('remove');

        $this->expectException(OnboardingAccountMismatchException::class);

        $this->processor->process($paymentMethod, 'AUTH_CODE', 'SHARED_ID');
    }

    public function test_it_reports_an_expired_onboarding_session(): void
    {
        $this->sellerNonceManager->method('get')->willReturn(null);

        $this->sellerOnboardingResolver->expects(self::never())->method('resolve');

        $this->expectException(OnboardingSessionExpiredException::class);

        $this->processor->process($this->paymentMethod(self::CONFIG), 'AUTH_CODE', 'SHARED_ID');
    }

    public function test_it_reports_an_onboarding_paypal_could_not_resolve(): void
    {
        $this->sellerNonceManager->method('get')->willReturn('NONCE');
        $this->sellerOnboardingResolver->method('resolve')->willThrowException(new \RuntimeException('PayPal is down'));

        $this->onboardingScopeUpdater->expects(self::never())->method('update');

        $this->expectException(OnboardingFailedException::class);

        $this->processor->process($this->paymentMethod(self::CONFIG), 'AUTH_CODE', 'SHARED_ID');
    }

    private function sellerOnboardingResult(string $merchantId): SellerOnboardingResult
    {
        return new SellerOnboardingResult('NEW_CLIENT_ID', 'NEW_CLIENT_SECRET', $merchantId, new OnboardingStatus(true, true));
    }

    /** @param array<string, mixed> $config */
    private function paymentMethod(array $config): PaymentMethodInterface
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius_paypal');
        $gatewayConfig->setConfig($config);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        return $paymentMethod;
    }
}
