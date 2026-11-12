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

namespace Tests\Sylius\PayPalPlugin\Unit\Updater;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Model\OnboardingScope;
use Sylius\PayPalPlugin\Updater\OnboardingScopeUpdater;

final class OnboardingScopeUpdaterTest extends TestCase
{
    private ObjectManager&MockObject $paymentMethodManager;

    private OnboardingScopeUpdater $updater;

    protected function setUp(): void
    {
        $this->paymentMethodManager = $this->createMock(ObjectManager::class);
        $this->updater = new OnboardingScopeUpdater($this->paymentMethodManager);
    }

    public function test_it_stores_the_current_onboarding_scope_in_the_gateway_config(): void
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setConfig(['client_id' => 'CLIENT_ID', 'onboarding_scope' => ['product' => OnboardingScope::PRODUCT_PPCP]]);
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        $this->paymentMethodManager->expects(self::once())->method('flush');

        $this->updater->update($paymentMethod);

        self::assertSame(['client_id' => 'CLIENT_ID', 'onboarding_scope' => OnboardingScope::current()->toArray()], $gatewayConfig->getConfig());
    }

    public function test_it_does_nothing_for_a_payment_method_without_a_gateway_config(): void
    {
        $this->paymentMethodManager->expects(self::never())->method('flush');

        $this->updater->update(new PaymentMethod());
    }
}
