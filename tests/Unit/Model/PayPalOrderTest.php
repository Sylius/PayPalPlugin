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

    protected function setUp(): void
    {
        parent::setUp();
        $this->order = $this->createMock(OrderInterface::class);
        $this->payPalPurchaseUnit = $this->createMock(PayPalPurchaseUnit::class);
    }

    public function test_it_sends_the_given_payment_source_as_it_is(): void
    {
        $this->payPalPurchaseUnit->method('toArray')->willReturn(['reference_id' => 'REFERENCE_ID']);

        $paymentSource = ['paypal' => ['experience_context' => ['locale' => 'en-US', 'user_action' => 'PAY_NOW']]];

        $payPalOrder = new PayPalOrder(
            order: $this->order,
            payPalPurchaseUnit: $this->payPalPurchaseUnit,
            intent: PayPalOrder::INTENT_CAPTURE,
            paymentSource: $paymentSource,
        );

        self::assertSame([
            'intent' => 'CAPTURE',
            'purchase_units' => [
                ['reference_id' => 'REFERENCE_ID'],
            ],
            'payment_source' => $paymentSource,
        ], $payPalOrder->toArray());
    }

    public function test_it_carries_a_payment_source_other_than_paypal(): void
    {
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $paymentSource = ['google_pay' => ['attributes' => ['verification' => ['method' => 'SCA_WHEN_REQUIRED']]]];

        $payPalOrder = new PayPalOrder(
            order: $this->order,
            payPalPurchaseUnit: $this->payPalPurchaseUnit,
            intent: PayPalOrder::INTENT_CAPTURE,
            paymentSource: $paymentSource,
        );

        self::assertSame($paymentSource, $payPalOrder->toArray()['payment_source']);
    }

    public function test_it_omits_the_payment_source_when_there_is_nothing_to_send(): void
    {
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $payPalOrder = new PayPalOrder(
            order: $this->order,
            payPalPurchaseUnit: $this->payPalPurchaseUnit,
            intent: PayPalOrder::INTENT_CAPTURE,
            paymentSource: [],
        );

        self::assertArrayNotHasKey('payment_source', $payPalOrder->toArray());
    }

    public function test_it_never_sends_the_legacy_application_context(): void
    {
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $payPalOrder = new PayPalOrder(
            order: $this->order,
            payPalPurchaseUnit: $this->payPalPurchaseUnit,
            intent: PayPalOrder::INTENT_CAPTURE,
            paymentSource: ['paypal' => ['experience_context' => []]],
        );

        $result = $payPalOrder->toArray();

        self::assertArrayHasKey('payment_source', $result);
        self::assertArrayNotHasKey('application_context', $result);
    }

    public function test_it_sends_no_processing_instruction_unless_it_is_given_one(): void
    {
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $payPalOrder = new PayPalOrder(
            order: $this->order,
            payPalPurchaseUnit: $this->payPalPurchaseUnit,
            intent: PayPalOrder::INTENT_CAPTURE,
            paymentSource: ['paypal' => ['experience_context' => []]],
        );

        self::assertArrayNotHasKey('processing_instruction', $payPalOrder->toArray());
    }

    public function test_it_asks_paypal_to_complete_the_order_on_payment_approval(): void
    {
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $payPalOrder = new PayPalOrder(
            order: $this->order,
            payPalPurchaseUnit: $this->payPalPurchaseUnit,
            intent: PayPalOrder::INTENT_CAPTURE,
            paymentSource: ['trustly' => []],
            processingInstruction: PayPalOrder::PROCESSING_INSTRUCTION_ORDER_COMPLETE_ON_PAYMENT_APPROVAL,
        );

        self::assertSame(
            'ORDER_COMPLETE_ON_PAYMENT_APPROVAL',
            $payPalOrder->toArray()['processing_instruction'],
        );
    }

    public function test_it_sends_the_legacy_application_context_when_no_payment_source_is_given(): void
    {
        $this->payPalPurchaseUnit->method('toArray')->willReturn(['reference_id' => 'REFERENCE_ID']);
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn($this->createMock(AddressInterface::class));

        $payPalOrder = new PayPalOrder($this->order, $this->payPalPurchaseUnit, PayPalOrder::INTENT_CAPTURE);

        self::assertSame([
            'intent' => 'CAPTURE',
            'purchase_units' => [
                ['reference_id' => 'REFERENCE_ID'],
            ],
            'application_context' => ['shipping_preference' => 'SET_PROVIDED_ADDRESS'],
        ], $payPalOrder->toArray());
    }

    public function test_it_lets_paypal_collect_the_address_in_the_legacy_application_context_when_the_order_has_none(): void
    {
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);

        $payPalOrder = new PayPalOrder($this->order, $this->payPalPurchaseUnit, PayPalOrder::INTENT_CAPTURE);

        self::assertSame(['shipping_preference' => 'GET_FROM_FILE'], $payPalOrder->toArray()['application_context']);
    }

    public function test_it_asks_for_no_shipping_in_the_legacy_application_context_when_the_order_needs_none(): void
    {
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);
        $this->order->method('isShippingRequired')->willReturn(false);

        $payPalOrder = new PayPalOrder($this->order, $this->payPalPurchaseUnit, PayPalOrder::INTENT_CAPTURE);

        self::assertSame(['shipping_preference' => 'NO_SHIPPING'], $payPalOrder->toArray()['application_context']);
        self::assertArrayNotHasKey('payment_source', $payPalOrder->toArray());
    }
}
