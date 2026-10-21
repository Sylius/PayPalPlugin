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
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\PayPalPlugin\Updater\WebhookIdUpdater;
use Sylius\PayPalPlugin\Updater\WebhookIdUpdaterInterface;

final class WebhookIdUpdaterTest extends TestCase
{
    private ObjectManager&MockObject $paymentMethodManager;

    private WebhookIdUpdater $updater;

    protected function setUp(): void
    {
        $this->paymentMethodManager = $this->createMock(ObjectManager::class);
        $this->updater = new WebhookIdUpdater($this->paymentMethodManager);
    }

    public function test_it_implements_the_webhook_id_updater_interface(): void
    {
        self::assertInstanceOf(WebhookIdUpdaterInterface::class, $this->updater);
    }

    public function test_it_stores_the_webhook_id_in_the_gateway_config_of_the_payment_method(): void
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setConfig(['client_id' => 'CLIENT_ID', 'webhook_id' => 'WH-STALE']);
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        $this->paymentMethodManager->expects(self::once())->method('flush');

        $this->updater->update($paymentMethod, 'WH-CURRENT');

        self::assertSame(['client_id' => 'CLIENT_ID', 'webhook_id' => 'WH-CURRENT'], $gatewayConfig->getConfig());
    }

    public function test_it_does_nothing_for_a_payment_method_without_a_gateway_config(): void
    {
        $this->paymentMethodManager->expects(self::never())->method('flush');

        $this->updater->update(new PaymentMethod(), 'WH-CURRENT');
    }
}
