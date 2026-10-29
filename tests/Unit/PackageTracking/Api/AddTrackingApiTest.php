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

namespace Tests\Sylius\PayPalPlugin\Unit\PackageTracking\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\PackageTracking\Api\AddTrackingApi;
use Sylius\PayPalPlugin\PackageTracking\Api\AddTrackingApiInterface;

final class AddTrackingApiTest extends TestCase
{
    private PayPalClientInterface&MockObject $client;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private AddTrackingApi $addTrackingApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PayPalClientInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->addTrackingApi = new AddTrackingApi($this->client);
    }

    #[Test]
    public function it_implements_add_tracking_api_interface(): void
    {
        self::assertInstanceOf(AddTrackingApiInterface::class, $this->addTrackingApi);
    }

    #[Test]
    public function it_posts_tracking_information_to_the_order_track_endpoint(): void
    {
        $body = [
            'capture_id' => 'CAP123',
            'tracking_number' => 'TRACK1',
            'carrier' => 'FEDEX',
            'notify_payer' => true,
            'items' => [['name' => 'T-Shirt', 'quantity' => '1', 'sku' => 'sku01']],
        ];

        $this->client
            ->expects(self::once())
            ->method('post')
            ->with($this->paymentMethod, 'v2/checkout/orders/ORDER123/track', $body)
            ->willReturn(['id' => 'ORDER123', 'status' => 'COMPLETED']);

        $result = $this->addTrackingApi->add($this->paymentMethod, 'ORDER123', $body);

        self::assertSame(['id' => 'ORDER123', 'status' => 'COMPLETED'], $result);
    }
}
