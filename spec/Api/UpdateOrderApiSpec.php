<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Api;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Provider\PaymentReferenceNumberProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProviderInterface;

final class UpdateOrderApiSpec extends ObjectBehavior
{
    function let(
        PayPalClientInterface $client,
        PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
        PayPalItemDataProviderInterface $payPalItemsDataProvider
    ): void {
        $this->beConstructedWith($client, $paymentReferenceNumberProvider, $payPalItemsDataProvider);
    }

    function it_implements_update_order_api_interface(): void
    {
        $this->shouldImplement(UpdateOrderApiInterface::class);
    }

    function it_updates_pay_pal_order_with_given_new_total(
        PayPalClientInterface $client,
        PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
        PayPalItemDataProviderInterface $payPalItemsDataProvider,
        PaymentInterface $payment,
        OrderInterface $order,
        AddressInterface $shippingAddress
    ): void {
        $payment->getOrder()->willReturn($order);
        $order->getShippingAddress()->willReturn($shippingAddress);
        $payment->getAmount()->willReturn(1122);

        $payPalItemsDataProvider
            ->provide($order)
            ->willReturn(['items' => ['data'], 'total_item_value' => '10.00', 'total_tax' => '1.00'])
        ;

        $paymentReferenceNumberProvider->provide($payment)->willReturn('INVOICE_NUMBER');

        $order->getTotal()->willReturn(1122);
        $order->getCurrencyCode()->willReturn('USD');
        $order->getShippingTotal()->willReturn(22);
        $order->getOrderPromotionTotal()->willReturn(0);

        $shippingAddress->getFullName()->willReturn('John Doe');
        $shippingAddress->getStreet()->willReturn('Main St. 123');
        $shippingAddress->getCity()->willReturn('New York');
        $shippingAddress->getPostcode()->willReturn('10001');
        $shippingAddress->getCountryCode()->willReturn('US');

        $order->isShippingRequired()->willReturn(true);

        $client->patch(
            'v2/checkout/orders/ORDER-ID',
            'TOKEN',
            Argument::that(function (array $data): bool {
                return
                    $data[0]['op'] === 'replace' &&
                    $data[0]['path'] === '/purchase_units/@reference_id==\'REFERENCE-ID\'' &&
                    $data[0]['value']['reference_id'] === 'REFERENCE-ID' &&
                    $data[0]['value']['invoice_number'] === 'INVOICE_NUMBER' &&
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
            })
        )->shouldBeCalled();

        $this->update('TOKEN', 'ORDER-ID', $payment, 'REFERENCE-ID', 'MERCHANT-ID');
    }

    function it_updates_digital_order(
        PayPalClientInterface $client,
        PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
        PayPalItemDataProviderInterface $payPalItemsDataProvider,
        PaymentInterface $payment,
        OrderInterface $order,
        AddressInterface $shippingAddress
    ): void {
        $payment->getOrder()->willReturn($order);
        $order->getShippingAddress()->willReturn($shippingAddress);
        $payment->getAmount()->willReturn(1122);

        $payPalItemsDataProvider
            ->provide($order)
            ->willReturn(['items' => ['data'], 'total_item_value' => '10.00', 'total_tax' => '1.22'])
        ;

        $paymentReferenceNumberProvider->provide($payment)->willReturn('INVOICE_NUMBER');

        $order->getTotal()->willReturn(1122);
        $order->getCurrencyCode()->willReturn('USD');
        $order->getShippingTotal()->willReturn(0);
        $order->getOrderPromotionTotal()->willReturn(0);

        $order->isShippingRequired()->willReturn(false);

        $client->patch(
            'v2/checkout/orders/ORDER-ID',
            'TOKEN',
            Argument::that(function (array $data): bool {
                return
                    $data[0]['op'] === 'replace' &&
                    $data[0]['path'] === '/purchase_units/@reference_id==\'REFERENCE-ID\'' &&
                    $data[0]['value']['reference_id'] === 'REFERENCE-ID' &&
                    $data[0]['value']['invoice_number'] === 'INVOICE_NUMBER' &&
                    $data[0]['value']['amount']['value'] === '11.22' &&
                    $data[0]['value']['amount']['currency_code'] === 'USD' &&
                    $data[0]['value']['amount']['breakdown']['shipping']['value'] === '0.00' &&
                    $data[0]['value']['amount']['breakdown']['item_total']['value'] === '10.00' &&
                    $data[0]['value']['amount']['breakdown']['tax_total']['value'] === '1.22' &&
                    $data[0]['value']['payee']['merchant_id'] === 'MERCHANT-ID' &&
                    $data[0]['value']['items'] === ['data']
                ;
            })
        )->shouldBeCalled();

        $this->update('TOKEN', 'ORDER-ID', $payment, 'REFERENCE-ID', 'MERCHANT-ID');
    }
}
