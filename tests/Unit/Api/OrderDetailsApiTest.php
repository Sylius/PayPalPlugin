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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApi;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Mapper\PayPalOrderDetailsMapperInterface;
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;

final class OrderDetailsApiTest extends TestCase
{
    private PayPalClientInterface&MockObject $client;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private PayPalOrderDetailsMapperInterface&MockObject $payPalOrderDetailsMapper;

    private OrderDetailsApi $orderDetailsApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PayPalClientInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->payPalOrderDetailsMapper = $this->createMock(PayPalOrderDetailsMapperInterface::class);
        $this->orderDetailsApi = new OrderDetailsApi($this->client, $this->payPalOrderDetailsMapper);
    }

    #[Test]
    public function it_implements_paypal_order_details_provider_interface(): void
    {
        self::assertInstanceOf(OrderDetailsApiInterface::class, $this->orderDetailsApi);
    }

    #[Test]
    public function it_provides_details_about_paypal_order(): void
    {
        $this->client
            ->expects(self::once())
            ->method('get')
            ->with($this->paymentMethod, 'v2/checkout/orders/123123')
            ->willReturn(['id' => '123123', 'status' => 'APPROVED']);

        $payPalOrderDetails = new PayPalOrderDetails('123123', 'APPROVED');
        $this->payPalOrderDetailsMapper->expects(self::once())->method('map')->with(['id' => '123123', 'status' => 'APPROVED'])->willReturn($payPalOrderDetails);

        self::assertSame($payPalOrderDetails, $this->orderDetailsApi->get($this->paymentMethod, '123123'));
    }
}
