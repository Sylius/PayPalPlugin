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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\Processor\Webhook\VaultingCapabilityWebhookProcessor;
use Sylius\PayPalPlugin\Refresher\VaultingCapabilityRefresherInterface;

final class VaultingCapabilityWebhookProcessorTest extends TestCase
{
    /** @var PaymentMethodRepositoryInterface<PaymentMethodInterface>&MockObject */
    private PaymentMethodRepositoryInterface&MockObject $paymentMethodRepository;

    private VaultingCapabilityRefresherInterface&MockObject $refresher;

    private VaultingCapabilityWebhookProcessor $processor;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $this->refresher = $this->createMock(VaultingCapabilityRefresherInterface::class);

        $this->processor = new VaultingCapabilityWebhookProcessor($this->paymentMethodRepository, $this->refresher);
    }

    public function test_it_supports_the_capability_updated_event_only(): void
    {
        self::assertTrue($this->processor->supports('CUSTOMER.MERCHANT-INTEGRATION.CAPABILITY-UPDATED'));
        self::assertFalse($this->processor->supports('VAULT.PAYMENT-TOKEN.CREATED'));
    }

    public function test_it_refreshes_the_paypal_payment_methods_of_the_merchant_whose_capability_changed(): void
    {
        $merchantMethod = $this->paymentMethod('sylius_paypal', 'MERCHANT_ID');
        $this->paymentMethodRepository->method('findAll')->willReturn([
            $merchantMethod,
            $this->paymentMethod('sylius_paypal', 'OTHER_MERCHANT_ID'),
            $this->paymentMethod('offline', null),
        ]);

        $this->refresher->expects(self::once())->method('refresh')->with($merchantMethod);

        $this->processor->process($this->event(['merchant_id' => 'MERCHANT_ID']));
    }

    public function test_it_refreshes_every_paypal_payment_method_when_the_event_names_no_merchant(): void
    {
        $this->paymentMethodRepository->method('findAll')->willReturn([
            $this->paymentMethod('sylius_paypal', 'MERCHANT_ID'),
            $this->paymentMethod('sylius_paypal', 'OTHER_MERCHANT_ID'),
            $this->paymentMethod('offline', null),
        ]);

        $this->refresher->expects(self::exactly(2))->method('refresh');

        $this->processor->process($this->event([]));
    }

    public function test_it_lets_paypal_deliver_the_event_again_when_the_refresh_fails(): void
    {
        $this->paymentMethodRepository->method('findAll')->willReturn([$this->paymentMethod('sylius_paypal', 'MERCHANT_ID')]);
        $this->refresher->method('refresh')->willThrowException(new \RuntimeException('PayPal is down'));

        $this->expectException(\RuntimeException::class);

        $this->processor->process($this->event(['merchant_id' => 'MERCHANT_ID']));
    }

    /**
     * @param array<string, mixed> $resource
     *
     * @return array<string, mixed>
     */
    private function event(array $resource): array
    {
        return ['event_type' => 'CUSTOMER.MERCHANT-INTEGRATION.CAPABILITY-UPDATED', 'resource' => $resource];
    }

    private function paymentMethod(string $factoryName, ?string $merchantId): PaymentMethod
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName($factoryName);
        $gatewayConfig->setConfig(null !== $merchantId ? ['merchant_id' => $merchantId] : []);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        return $paymentMethod;
    }
}
