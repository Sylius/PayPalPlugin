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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\WebhookResourceApi;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiNotFoundException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookResourceNotSupportedException;
use Sylius\PayPalPlugin\Exception\PermanentWebhookFailureInterface;

final class WebhookResourceApiTest extends TestCase
{
    private PayPalClientInterface&MockObject $client;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private WebhookResourceApi $api;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PayPalClientInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $this->api = new WebhookResourceApi($this->client);
    }

    #[DataProvider('resources')]
    public function test_it_reads_the_resource_of_a_webhook_event_from_paypal(string $resourceType, string $url): void
    {
        $this->client->expects(self::once())->method('get')->with($this->paymentMethod, $url)->willReturn(['id' => 'RESOURCE_ID', 'status' => 'COMPLETED']);

        self::assertSame(['id' => 'RESOURCE_ID', 'status' => 'COMPLETED'], $this->api->get($this->paymentMethod, $resourceType, 'RESOURCE_ID'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function resources(): iterable
    {
        yield 'capture' => ['capture', 'v2/payments/captures/RESOURCE_ID'];
        yield 'checkout order' => ['checkout-order', 'v2/checkout/orders/RESOURCE_ID'];
        yield 'refund' => ['refund', 'v2/payments/refunds/RESOURCE_ID'];
    }

    public function test_it_keeps_the_resource_id_inside_the_url_path(): void
    {
        $this->client->expects(self::once())->method('get')->with($this->paymentMethod, 'v2/payments/captures/..%2F..%2Fv1%2Fother')->willReturn(['id' => '../../v1/other']);

        $this->api->get($this->paymentMethod, 'capture', '../../v1/other');
    }

    public function test_it_reports_a_resource_paypal_did_not_return_so_the_event_is_delivered_again(): void
    {
        $this->client->method('get')->willThrowException(new PayPalApiNotFoundException('GET', 'v2/payments/captures/RESOURCE_ID', 404));

        try {
            $this->api->get($this->paymentMethod, 'capture', 'RESOURCE_ID');
            self::fail('A resource PayPal did not return must not be processed.');
        } catch (PayPalApiNotFoundException $exception) {
            self::assertNotInstanceOf(PermanentWebhookFailureInterface::class, $exception);
        }
    }

    public function test_it_gives_up_on_a_resource_type_it_cannot_read(): void
    {
        $this->client->expects(self::never())->method('get');

        $this->expectException(PayPalWebhookResourceNotSupportedException::class);

        $this->api->get($this->paymentMethod, 'dispute', 'RESOURCE_ID');
    }
}
