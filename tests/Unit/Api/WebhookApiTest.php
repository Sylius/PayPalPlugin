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
use Sylius\PayPalPlugin\Api\WebhookApi;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class WebhookApiTest extends TestCase
{
    private PayPalClientInterface&MockObject $client;

    private WebhookApi $webhookApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PayPalClientInterface::class);

        $this->webhookApi = new WebhookApi($this->client);
    }

    public function test_it_registers_a_webhook_for_every_event_the_plugin_handles(): void
    {
        $this->client
            ->expects(self::once())
            ->method('post')
            ->with('v1/notifications/webhooks', 'TOKEN', [
                'url' => 'https://shop.example.com/paypal-webhook/api/',
                'event_types' => [
                    ['name' => 'PAYMENT.CAPTURE.REFUNDED'],
                    ['name' => 'PAYMENT.CAPTURE.COMPLETED'],
                    ['name' => 'PAYMENT.CAPTURE.DENIED'],
                    ['name' => 'PAYMENT.CAPTURE.DECLINED'],
                    ['name' => 'PAYMENT.CAPTURE.PENDING'],
                ],
            ])
            ->willReturn(['id' => 'WEBHOOK_ID'])
        ;

        self::assertSame(['id' => 'WEBHOOK_ID'], $this->webhookApi->register('TOKEN', 'https://shop.example.com/paypal-webhook/api/'));
    }

    public function test_it_registers_the_webhook_over_https(): void
    {
        $this->client
            ->expects(self::once())
            ->method('post')
            ->with('v1/notifications/webhooks', 'TOKEN', self::callback(static fn (array $data): bool => 'https://shop.example.com/paypal-webhook/api/' === $data['url']))
            ->willReturn([])
        ;

        $this->webhookApi->register('TOKEN', 'http://shop.example.com/paypal-webhook/api/');
    }
}
