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

namespace Tests\Sylius\PayPalPlugin\Unit\Processor\Webhook;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Processor\Webhook\VaultingCapabilityWebhookProcessor;
use Sylius\PayPalPlugin\Processor\Webhook\VaultingCapabilityWebhookProcessorInterface;
use Sylius\PayPalPlugin\Refresher\VaultingCapabilityRefresherInterface;

final class VaultingCapabilityWebhookProcessorTest extends TestCase
{
    private VaultingCapabilityRefresherInterface&MockObject $refresher;

    private ObjectManager&MockObject $paymentMethodManager;

    private VaultingCapabilityWebhookProcessor $processor;

    protected function setUp(): void
    {
        $this->refresher = $this->createMock(VaultingCapabilityRefresherInterface::class);
        $this->paymentMethodManager = $this->createMock(ObjectManager::class);

        $this->processor = new VaultingCapabilityWebhookProcessor($this->refresher, $this->paymentMethodManager);
    }

    public function test_it_implements_the_vaulting_capability_webhook_processor_interface(): void
    {
        self::assertInstanceOf(VaultingCapabilityWebhookProcessorInterface::class, $this->processor);
    }

    public function test_it_refreshes_and_stores_the_payment_method_of_the_merchant_whose_capability_changed(): void
    {
        $paymentMethod = $this->paymentMethod(['client_id' => 'CLIENT_ID', 'client_secret' => 'SECRET', 'merchant_id' => 'MERCHANT_ID']);

        $this->refresher->expects(self::once())->method('refresh')->with($paymentMethod);
        $this->paymentMethodManager->expects(self::once())->method('flush');

        $this->processor->process(['merchant_id' => 'MERCHANT_ID'], $paymentMethod);
    }

    public function test_it_refreshes_the_payment_method_when_the_event_names_no_merchant(): void
    {
        $this->refresher->expects(self::once())->method('refresh');

        $this->processor->process([], $this->paymentMethod(['client_id' => 'CLIENT_ID', 'client_secret' => 'SECRET', 'merchant_id' => 'MERCHANT_ID']));
    }

    public function test_it_leaves_a_payment_method_of_another_merchant_alone(): void
    {
        $this->refresher->expects(self::never())->method('refresh');
        $this->paymentMethodManager->expects(self::never())->method('flush');

        $this->processor->process(
            ['merchant_id' => 'OTHER_MERCHANT_ID'],
            $this->paymentMethod(['client_id' => 'CLIENT_ID', 'client_secret' => 'SECRET', 'merchant_id' => 'MERCHANT_ID']),
        );
    }

    public function test_it_skips_a_payment_method_without_credentials_to_ask_paypal_with(): void
    {
        $this->refresher->expects(self::never())->method('refresh');

        $this->processor->process(['merchant_id' => 'MERCHANT_ID'], $this->paymentMethod(['merchant_id' => 'MERCHANT_ID']));
    }

    public function test_it_lets_a_failed_refresh_through_so_paypal_delivers_the_event_again(): void
    {
        $this->refresher->method('refresh')->willThrowException(new \RuntimeException('PayPal is down'));
        $this->paymentMethodManager->expects(self::never())->method('flush');

        $this->expectExceptionMessage('PayPal is down');

        $this->processor->process([], $this->paymentMethod(['client_id' => 'CLIENT_ID', 'client_secret' => 'SECRET', 'merchant_id' => 'MERCHANT_ID']));
    }

    /** @param array<string, mixed> $config */
    private function paymentMethod(array $config): PaymentMethod
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius_paypal');
        $gatewayConfig->setConfig($config);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        return $paymentMethod;
    }
}
