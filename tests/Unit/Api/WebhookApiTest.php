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

namespace Tests\Sylius\PayPalPlugin\Unit\Api;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\WebhookApi;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class WebhookApiTest extends TestCase
{
    private PayPalClientInterface&MockObject $client;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private WebhookApi $webhookApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PayPalClientInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $this->webhookApi = new WebhookApi($this->client);
    }

    public function test_it_registers_a_webhook_for_every_event_the_plugin_handles(): void
    {
        $this->client
            ->expects(self::once())
            ->method('post')
            ->with($this->paymentMethod, 'v1/notifications/webhooks', [
                'url' => 'https://shop.example.com/payment-methods/PAYPAL',
                'event_types' => [
                    ['name' => 'CHECKOUT.ORDER.APPROVED'],
                    ['name' => 'CHECKOUT.ORDER.COMPLETED'],
                    ['name' => 'PAYMENT.CAPTURE.COMPLETED'],
                    ['name' => 'PAYMENT.CAPTURE.DENIED'],
                    ['name' => 'PAYMENT.CAPTURE.DECLINED'],
                    ['name' => 'PAYMENT.CAPTURE.PENDING'],
                    ['name' => 'PAYMENT.CAPTURE.REFUNDED'],
                    ['name' => 'PAYMENT.CAPTURE.REVERSED'],
                    ['name' => 'VAULT.PAYMENT-TOKEN.CREATED'],
                    ['name' => 'VAULT.PAYMENT-TOKEN.DELETED'],
                    ['name' => 'CUSTOMER.MERCHANT-INTEGRATION.CAPABILITY-UPDATED'],
                ],
            ])
            ->willReturn(['id' => 'WEBHOOK_ID'])
        ;

        self::assertSame(['id' => 'WEBHOOK_ID'], $this->webhookApi->register($this->paymentMethod, 'https://shop.example.com/payment-methods/PAYPAL'));
    }

    public function test_it_registers_the_webhook_over_https(): void
    {
        $this->client
            ->expects(self::once())
            ->method('post')
            ->with($this->paymentMethod, 'v1/notifications/webhooks', self::callback(static fn (array $data): bool => 'https://shop.example.com/payment-methods/PAYPAL' === $data['url']))
            ->willReturn([])
        ;

        $this->webhookApi->register($this->paymentMethod, 'http://shop.example.com/payment-methods/PAYPAL');
    }
}
