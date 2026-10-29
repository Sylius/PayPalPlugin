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
use Sylius\PayPalPlugin\Api\UpdateWebhookApi;
use Sylius\PayPalPlugin\Api\UpdateWebhookApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class UpdateWebhookApiTest extends TestCase
{
    private PayPalClientInterface&MockObject $client;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private UpdateWebhookApi $api;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PayPalClientInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $this->api = new UpdateWebhookApi($this->client);
    }

    public function test_it_implements_the_update_webhook_api_interface(): void
    {
        self::assertInstanceOf(UpdateWebhookApiInterface::class, $this->api);
    }

    public function test_it_replaces_the_url_and_the_event_types_of_the_webhook(): void
    {
        $this->client
            ->expects(self::once())
            ->method('patch')
            ->with($this->paymentMethod, 'v1/notifications/webhooks/WEBHOOK_ID', [
                [
                    'op' => 'replace',
                    'path' => '/url',
                    'value' => 'https://shop.example.com/payment-methods/PAYPAL',
                ],
                [
                    'op' => 'replace',
                    'path' => '/event_types',
                    'value' => [['name' => 'A'], ['name' => 'B']],
                ],
            ])
            ->willReturn(['id' => 'WEBHOOK_ID'])
        ;

        self::assertSame(['id' => 'WEBHOOK_ID'], $this->api->update($this->paymentMethod, 'WEBHOOK_ID', 'http://shop.example.com/payment-methods/PAYPAL', ['A', 'B']));
    }
}
