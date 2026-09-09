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
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderApi;
use Sylius\PayPalPlugin\Api\UpdateOrderApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Factory\PayPalPurchaseUnitFactory;
use Sylius\PayPalPlugin\Provider\PaymentReferenceNumberProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProviderInterface;

final class UpdateOrderApiTest extends TestCase
{
    private PayPalClientInterface&MockObject $client;

    private PaymentReferenceNumberProviderInterface&MockObject $paymentReferenceNumberProvider;

    private PayPalItemDataProviderInterface&MockObject $payPalItemsDataProvider;

    private UpdateOrderApi $updateOrderApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PayPalClientInterface::class);
        $this->paymentReferenceNumberProvider = $this->createMock(PaymentReferenceNumberProviderInterface::class);
        $this->payPalItemsDataProvider = $this->createMock(PayPalItemDataProviderInterface::class);

        $this->updateOrderApi = new UpdateOrderApi(
            $this->client,
            $this->paymentReferenceNumberProvider,
            $this->payPalItemsDataProvider,
            new PayPalPurchaseUnitFactory($this->paymentReferenceNumberProvider, $this->payPalItemsDataProvider),
        );
    }

    #[Test]
    public function it_implements_update_order_api_interface(): void
    {
        self::assertInstanceOf(UpdateOrderApiInterface::class, $this->updateOrderApi);
    }

    #[Test]
    public function it_updates_paypal_order_with_given_new_total(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $shippingAddress = $this->createMock(AddressInterface::class);

        $payment->method('getOrder')->willReturn($order);
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $payment->method('getAmount')->willReturn(1122);

        $this->payPalItemsDataProvider
            ->method('provide')
            ->with($order)
            ->willReturn(['items' => ['data'], 'total_item_value' => '10.00', 'total_tax' => '1.00']);

        $this->paymentReferenceNumberProvider
            ->method('provide')
            ->with($payment)
            ->willReturn('INVOICE_ID');

        $order->method('getTotal')->willReturn(1122);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getShippingTotal')->willReturn(22);
        $order->method('getOrderPromotionTotal')->willReturn(0);
        $order->method('getAdjustmentsTotalRecursively')
            ->with(AdjustmentInterface::ORDER_SHIPPING_PROMOTION_ADJUSTMENT)
            ->willReturn(0);

        $shippingAddress->method('getFullName')->willReturn('John Doe');
        $shippingAddress->method('getStreet')->willReturn('Main St. 123');
        $shippingAddress->method('getCity')->willReturn('New York');
        $shippingAddress->method('getPostcode')->willReturn('10001');
        $shippingAddress->method('getCountryCode')->willReturn('US');

        $order->method('isShippingRequired')->willReturn(true);

        $this->client
            ->expects(self::once())
            ->method('patch')
            ->with(
                'v2/checkout/orders/ORDER-ID',
                'TOKEN',
                $this->callback(function (array $data): bool {
                    return
                        $data[0]['op'] === 'replace' &&
                        $data[0]['path'] === '/purchase_units/@reference_id==\'REFERENCE-ID\'' &&
                        $data[0]['value']['reference_id'] === 'REFERENCE-ID' &&
                        $data[0]['value']['invoice_id'] === 'INVOICE_ID' &&
                        $data[0]['value']['amount']['value'] === '11.22' &&
                        $data[0]['value']['amount']['currency_code'] === 'USD' &&
                        $data[0]['value']['amount']['breakdown']['shipping']['value'] === '0.22' &&
                        $data[0]['value']['amount']['breakdown']['item_total']['value'] === '10.00' &&
                        $data[0]['value']['amount']['breakdown']['tax_total']['value'] === '1.00' &&
                        $data[0]['value']['payee']['merchant_id'] === 'MERCHANT-ID' &&
                        $data[0]['value']['shipping']['name']['full_name'] === 'John Doe' &&
                        $data[0]['value']['shipping']['address']['address_line_1'] === 'Main St. 123' &&
                        $data[0]['value']['shipping']['address']['admin_area_2'] === 'New York' &&
                        $data[0]['value']['shipping']['address']['postal_code'] === '10001' &&
                        $data[0]['value']['shipping']['address']['country_code'] === 'US' &&
                        $data[0]['value']['items'] === ['data']
                    ;
                }),
            );

        $this->updateOrderApi->update('TOKEN', 'ORDER-ID', $payment, 'REFERENCE-ID', 'MERCHANT-ID');
    }

    #[Test]
    public function it_updates_digital_order(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $shippingAddress = $this->createMock(AddressInterface::class);

        $payment->method('getOrder')->willReturn($order);
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $payment->method('getAmount')->willReturn(1122);

        $this->payPalItemsDataProvider
            ->method('provide')
            ->with($order)
            ->willReturn(['items' => ['data'], 'total_item_value' => '10.00', 'total_tax' => '1.22']);

        $this->paymentReferenceNumberProvider
            ->method('provide')
            ->with($payment)
            ->willReturn('INVOICE_ID');

        $order->method('getTotal')->willReturn(1122);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getShippingTotal')->willReturn(0);
        $order->method('getOrderPromotionTotal')->willReturn(0);
        $order->method('getAdjustmentsTotalRecursively')
            ->with(AdjustmentInterface::ORDER_SHIPPING_PROMOTION_ADJUSTMENT)
            ->willReturn(0);

        $order->method('isShippingRequired')->willReturn(false);

        $this->client
            ->expects(self::once())
            ->method('patch')
            ->with(
                'v2/checkout/orders/ORDER-ID',
                'TOKEN',
                $this->callback(function (array $data): bool {
                    return
                        $data[0]['op'] === 'replace' &&
                        $data[0]['path'] === '/purchase_units/@reference_id==\'REFERENCE-ID\'' &&
                        $data[0]['value']['reference_id'] === 'REFERENCE-ID' &&
                        $data[0]['value']['invoice_id'] === 'INVOICE_ID' &&
                        $data[0]['value']['amount']['value'] === '11.22' &&
                        $data[0]['value']['amount']['currency_code'] === 'USD' &&
                        $data[0]['value']['amount']['breakdown']['shipping']['value'] === '0.00' &&
                        $data[0]['value']['amount']['breakdown']['item_total']['value'] === '10.00' &&
                        $data[0]['value']['amount']['breakdown']['tax_total']['value'] === '1.22' &&
                        $data[0]['value']['payee']['merchant_id'] === 'MERCHANT-ID' &&
                        $data[0]['value']['items'] === ['data']
                    ;
                }),
            );

        $this->updateOrderApi->update('TOKEN', 'ORDER-ID', $payment, 'REFERENCE-ID', 'MERCHANT-ID');
    }
}
