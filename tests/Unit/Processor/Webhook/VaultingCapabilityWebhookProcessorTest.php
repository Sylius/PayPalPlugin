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
use Sylius\PayPalPlugin\Refresher\VaultingCapabilityRefresherInterface;
use Sylius\PayPalPlugin\Repository\Query\PayPalPaymentMethodQueryInterface;

final class VaultingCapabilityWebhookProcessorTest extends TestCase
{
    private PayPalPaymentMethodQueryInterface&MockObject $payPalPaymentMethodQuery;

    private VaultingCapabilityRefresherInterface&MockObject $refresher;

    private ObjectManager&MockObject $paymentMethodManager;

    private VaultingCapabilityWebhookProcessor $processor;

    protected function setUp(): void
    {
        $this->payPalPaymentMethodQuery = $this->createMock(PayPalPaymentMethodQueryInterface::class);
        $this->refresher = $this->createMock(VaultingCapabilityRefresherInterface::class);
        $this->paymentMethodManager = $this->createMock(ObjectManager::class);

        $this->processor = new VaultingCapabilityWebhookProcessor($this->payPalPaymentMethodQuery, $this->refresher, $this->paymentMethodManager);
    }

    public function test_it_supports_the_capability_updated_event_only(): void
    {
        self::assertTrue($this->processor->supports('CUSTOMER.MERCHANT-INTEGRATION.CAPABILITY-UPDATED'));
        self::assertFalse($this->processor->supports('VAULT.PAYMENT-TOKEN.CREATED'));
    }

    public function test_it_refreshes_and_stores_the_paypal_payment_methods_of_the_merchant_whose_capability_changed(): void
    {
        $merchantMethod = $this->paymentMethod('MERCHANT_ID');
        $this->payPalPaymentMethodQuery->method('findAll')->willReturn([$merchantMethod, $this->paymentMethod('OTHER_MERCHANT_ID')]);

        $this->refresher->expects(self::once())->method('refresh')->with($merchantMethod);
        $this->paymentMethodManager->expects(self::once())->method('flush');

        $this->processor->process($this->event(['merchant_id' => 'MERCHANT_ID']));
    }

    public function test_it_refreshes_every_paypal_payment_method_when_the_event_names_no_merchant(): void
    {
        $this->payPalPaymentMethodQuery->method('findAll')->willReturn([
            $this->paymentMethod('MERCHANT_ID'),
            $this->paymentMethod('OTHER_MERCHANT_ID'),
        ]);

        $this->refresher->expects(self::exactly(2))->method('refresh');

        $this->processor->process($this->event([]));
    }

    public function test_it_skips_a_paypal_payment_method_without_credentials_to_ask_paypal_with(): void
    {
        $incompleteMethod = $this->paymentMethod('MERCHANT_ID');
        $incompleteMethod->getGatewayConfig()?->setConfig(['merchant_id' => 'MERCHANT_ID']);
        $this->payPalPaymentMethodQuery->method('findAll')->willReturn([$incompleteMethod]);

        $this->refresher->expects(self::never())->method('refresh');

        $this->processor->process($this->event([]));
    }

    public function test_it_refreshes_the_other_methods_and_lets_paypal_deliver_the_event_again_when_one_refresh_fails(): void
    {
        $failingMethod = $this->paymentMethod('MERCHANT_ID');
        $otherMethod = $this->paymentMethod('MERCHANT_ID');
        $this->payPalPaymentMethodQuery->method('findAll')->willReturn([$failingMethod, $otherMethod]);
        $refreshed = [];
        $this->refresher->method('refresh')->willReturnCallback(static function (PaymentMethod $paymentMethod) use ($failingMethod, &$refreshed): bool {
            if ($failingMethod === $paymentMethod) {
                throw new \RuntimeException('PayPal is down');
            }
            $refreshed[] = $paymentMethod;

            return true;
        });
        $this->paymentMethodManager->expects(self::once())->method('flush');

        try {
            $this->processor->process($this->event(['merchant_id' => 'MERCHANT_ID']));
            self::fail('The failed refresh must reach PayPal as a failed delivery.');
        } catch (\RuntimeException $exception) {
            self::assertSame('PayPal is down', $exception->getMessage());
        }

        self::assertSame([$otherMethod], $refreshed);
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

    private function paymentMethod(string $merchantId): PaymentMethod
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius_paypal');
        $gatewayConfig->setConfig(['client_id' => 'CLIENT_ID', 'client_secret' => 'SECRET', 'merchant_id' => $merchantId]);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        return $paymentMethod;
    }
}
