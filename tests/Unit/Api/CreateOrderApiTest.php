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
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CreateOrderApi;
use Sylius\PayPalPlugin\Api\CreateOrderApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Factory\PayPalOrderFactoryInterface;
use Sylius\PayPalPlugin\Model\PayPalOrder;
use Sylius\PayPalPlugin\Model\PayPalPurchaseUnit;

final class CreateOrderApiTest extends TestCase
{
    private PayPalClientInterface&MockObject $client;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private PayPalOrderFactoryInterface&MockObject $payPalOrderFactory;

    private CreateOrderApi $createOrderApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PayPalClientInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->payPalOrderFactory = $this->createMock(PayPalOrderFactoryInterface::class);

        $this->createOrderApi = new CreateOrderApi($this->client, $this->payPalOrderFactory);
    }

    #[Test]
    public function it_implements_create_order_api_interface(): void
    {
        self::assertInstanceOf(CreateOrderApiInterface::class, $this->createOrderApi);
    }

    #[Test]
    public function it_posts_the_order_its_factory_built_for_the_payment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->payPalOrderFactory
            ->expects(self::once())
            ->method('create')
            ->with($payment, 'REFERENCE_ID')
            ->willReturn($payPalOrder = new PayPalOrder(
                $this->purchaseUnit(),
                PayPalOrder::INTENT_CAPTURE,
                ['paypal' => ['experience_context' => []]],
            ))
        ;

        $this->client
            ->expects(self::once())
            ->method('post')
            ->with($this->paymentMethod, 'v2/checkout/orders', $payPalOrder->toArray())
            ->willReturn(['status' => 'CREATED', 'id' => 123])
        ;

        self::assertSame(
            ['status' => 'CREATED', 'id' => 123],
            $this->createOrderApi->create($this->paymentMethod, $payment, 'REFERENCE_ID'),
        );
    }

    public function test_it_forwards_the_chosen_payment_source_to_the_order_factory(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->payPalOrderFactory
            ->expects(self::once())
            ->method('create')
            ->with($payment, 'REFERENCE_ID', 'google_pay')
            ->willReturn(new PayPalOrder($this->purchaseUnit(), PayPalOrder::INTENT_CAPTURE, []))
        ;

        $this->client->method('post')->willReturn(['status' => 'CREATED', 'id' => 123]);

        $this->createOrderApi->create($this->paymentMethod, $payment, 'REFERENCE_ID', 'google_pay');
    }

    public function test_it_names_the_order_with_the_given_custom_id(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->payPalOrderFactory->expects(self::once())->method('create')->with($payment, 'REFERENCE_ID', 'paypal', 'PAYMENT_REQUEST_HASH')->willReturn(new PayPalOrder($this->purchaseUnit(), PayPalOrder::INTENT_CAPTURE, []));
        $this->client->method('post')->willReturn(['status' => 'CREATED', 'id' => 123]);

        $this->createOrderApi->create($this->paymentMethod, $payment, 'REFERENCE_ID', customId: 'PAYMENT_REQUEST_HASH');
    }

    public function test_it_identifies_the_order_request_with_the_given_request_id(): void
    {
        $this->payPalOrderFactory->method('create')->willReturn($payPalOrder = new PayPalOrder($this->purchaseUnit(), PayPalOrder::INTENT_CAPTURE, []));
        $this->client->expects(self::once())->method('post')->with($this->paymentMethod, 'v2/checkout/orders', $payPalOrder->toArray(), ['PayPal-Request-Id' => 'PAYMENT_REQUEST_HASH'])->willReturn(['status' => 'CREATED', 'id' => 123]);

        $this->createOrderApi->create($this->paymentMethod, $this->createMock(PaymentInterface::class), 'REFERENCE_ID', requestId: 'PAYMENT_REQUEST_HASH');
    }

    public function test_it_leaves_the_request_id_to_the_client_when_it_is_given_none(): void
    {
        $this->payPalOrderFactory->method('create')->willReturn($payPalOrder = new PayPalOrder($this->purchaseUnit(), PayPalOrder::INTENT_CAPTURE, []));
        $this->client->expects(self::once())->method('post')->with($this->paymentMethod, 'v2/checkout/orders', $payPalOrder->toArray(), [])->willReturn(['status' => 'CREATED', 'id' => 123]);

        $this->createOrderApi->create($this->paymentMethod, $this->createMock(PaymentInterface::class), 'REFERENCE_ID');
    }

    private function purchaseUnit(): PayPalPurchaseUnit
    {
        return new PayPalPurchaseUnit(
            'REFERENCE_ID',
            'REFERENCE-NUMBER',
            'PLN',
            10000,
            0,
            100.00,
            0.00,
            0,
            'merchant-id',
            [],
            false,
        );
    }
}
