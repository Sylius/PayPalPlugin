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
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\CreateOrderApi;
use Sylius\PayPalPlugin\Api\CreateOrderApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Factory\PayPalOrderFactoryInterface;
use Sylius\PayPalPlugin\Model\PayPalOrder;
use Sylius\PayPalPlugin\Model\PayPalPurchaseUnit;
use Sylius\PayPalPlugin\Provider\PaymentReferenceNumberProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProviderInterface;

final class CreateOrderApiTest extends TestCase
{
    private PayPalClientInterface&MockObject $client;

    private PaymentReferenceNumberProviderInterface&MockObject $paymentReferenceNumberProvider;

    private PayPalItemDataProviderInterface&MockObject $payPalItemDataProvider;

    private PayPalOrderFactoryInterface&MockObject $payPalOrderFactory;

    private CreateOrderApi $createOrderApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PayPalClientInterface::class);
        $this->paymentReferenceNumberProvider = $this->createMock(PaymentReferenceNumberProviderInterface::class);
        $this->payPalItemDataProvider = $this->createMock(PayPalItemDataProviderInterface::class);
        $this->payPalOrderFactory = $this->createMock(PayPalOrderFactoryInterface::class);

        $this->createOrderApi = new CreateOrderApi(
            $this->client,
            $this->paymentReferenceNumberProvider,
            $this->payPalItemDataProvider,
            $this->payPalOrderFactory,
        );
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
        $order = $this->createMock(OrderInterface::class);
        $order->method('isShippingRequired')->willReturn(false);

        $this->payPalOrderFactory
            ->expects(self::once())
            ->method('create')
            ->with($payment, 'REFERENCE_ID')
            ->willReturn($payPalOrder = new PayPalOrder(
                $order,
                $this->purchaseUnit(),
                PayPalOrder::INTENT_CAPTURE,
            ))
        ;

        $this->client
            ->expects(self::once())
            ->method('post')
            ->with('v2/checkout/orders', 'TOKEN', $payPalOrder->toArray())
            ->willReturn(['status' => 'CREATED', 'id' => 123])
        ;

        self::assertSame(
            ['status' => 'CREATED', 'id' => 123],
            $this->createOrderApi->create('TOKEN', $payment, 'REFERENCE_ID'),
        );
    }

    #[Test]
    public function it_still_creates_an_order_when_it_is_given_no_factory(): void
    {
        $createOrderApi = new CreateOrderApi(
            $this->client,
            $this->paymentReferenceNumberProvider,
            $this->payPalItemDataProvider,
        );

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('PLN');
        $order->method('getShippingTotal')->willReturn(1000);
        $order->method('isShippingRequired')->willReturn(true);
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getOrderPromotionTotal')->willReturn(0);
        $order
            ->method('getAdjustmentsTotalRecursively')
            ->with(AdjustmentInterface::ORDER_SHIPPING_PROMOTION_ADJUSTMENT)
            ->willReturn(0)
        ;

        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn(
            ['merchant_id' => 'merchant-id', 'sylius_merchant_id' => 'sylius-merchant-id'],
        );
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getAmount')->willReturn(10000);

        $this->paymentReferenceNumberProvider->method('provide')->willReturn('REFERENCE-NUMBER');
        $this->payPalItemDataProvider->method('provide')->willReturn([
            'items' => [],
            'total_item_value' => '90.00',
            'total_tax' => '0.00',
        ]);

        $this->client
            ->expects(self::once())
            ->method('post')
            ->with(
                'v2/checkout/orders',
                'TOKEN',
                $this->callback(function (array $data): bool {
                    return
                        'CAPTURE' === $data['intent'] &&
                        'REFERENCE-NUMBER' === $data['purchase_units'][0]['invoice_id'] &&
                        '100.00' === $data['purchase_units'][0]['amount']['value'] &&
                        !array_key_exists('return_url', $data['payment_source']['paypal']['experience_context']) &&
                        !array_key_exists(
                            'order_update_callback_config',
                            $data['payment_source']['paypal']['experience_context'],
                        )
                    ;
                }),
            )
            ->willReturn(['status' => 'PAYER_ACTION_REQUIRED', 'id' => 123])
        ;

        $createOrderApi->create('TOKEN', $payment, 'REFERENCE_ID');
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
