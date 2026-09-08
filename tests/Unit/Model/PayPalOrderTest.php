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

namespace Tests\Sylius\PayPalPlugin\Unit\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Model\PayPalOrder;
use Sylius\PayPalPlugin\Model\PayPalPurchaseUnit;

final class PayPalOrderTest extends TestCase
{
    private OrderInterface&MockObject $order;

    private PayPalPurchaseUnit&MockObject $payPalPurchaseUnit;

    private PayPalOrder $payPalOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->order = $this->createMock(OrderInterface::class);
        $this->payPalPurchaseUnit = $this->createMock(PayPalPurchaseUnit::class);
        $this->payPalOrder = new PayPalOrder(
            $this->order,
            $this->payPalPurchaseUnit,
            'CAPTURE',
            'BRAND_NAME',
            'en-US',
            'https://example.com/pay-with-paypal/TOKEN/1',
            'https://example.com/pay-with-paypal/TOKEN/1',
        );
        $this->payPalPurchaseUnit->method('toArray')->willReturn(['purchase_unit_data']);
    }

    #[Test]
    public function it_uses_provided_address_and_retains_contact_when_address_is_known(): void
    {
        $shippingAddress = $this->createMock(AddressInterface::class);

        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn($shippingAddress);

        $result = $this->payPalOrder->toArray();

        self::assertSame([
            'intent' => 'CAPTURE',
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'brand_name' => 'BRAND_NAME',
                        'locale' => 'en-US',
                        'shipping_preference' => 'SET_PROVIDED_ADDRESS',
                        'contact_preference' => 'RETAIN_CONTACT_INFO',
                        'user_action' => 'PAY_NOW',
                        'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                        'return_url' => 'https://example.com/pay-with-paypal/TOKEN/1',
                        'cancel_url' => 'https://example.com/pay-with-paypal/TOKEN/1',
                        'app_switch_preference' => [
                            'launch_paypal_app' => true,
                        ],
                    ],
                ],
            ],
            'purchase_units' => [
                ['purchase_unit_data'],
            ],
        ], $result);
    }

    #[Test]
    public function it_gets_address_from_file_and_updates_contact_when_address_is_unknown(): void
    {
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);

        $result = $this->payPalOrder->toArray();

        self::assertSame('GET_FROM_FILE', $result['payment_source']['paypal']['experience_context']['shipping_preference']);
        self::assertSame('UPDATE_CONTACT_INFO', $result['payment_source']['paypal']['experience_context']['contact_preference']);
    }

    #[Test]
    public function it_disables_shipping_when_it_is_not_required(): void
    {
        $this->order->method('isShippingRequired')->willReturn(false);
        $this->order->method('getShippingAddress')->willReturn(null);

        $result = $this->payPalOrder->toArray();

        self::assertSame('NO_SHIPPING', $result['payment_source']['paypal']['experience_context']['shipping_preference']);
    }
}
