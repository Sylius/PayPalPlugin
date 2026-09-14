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
use Sylius\PayPalPlugin\Model\PayPalOrder;
use Sylius\PayPalPlugin\Model\PayPalPurchaseUnit;

final class PayPalOrderTest extends TestCase
{
    private PayPalPurchaseUnit&MockObject $payPalPurchaseUnit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payPalPurchaseUnit = $this->createMock(PayPalPurchaseUnit::class);
    }

    #[Test]
    public function it_sends_the_experience_context_when_paypal_supplies_the_shipping_address(): void
    {
        $this->payPalPurchaseUnit->method('toArray')->willReturn(['reference_id' => 'REFERENCE_ID']);

        $experienceContext = [
            'locale' => 'en-US',
            'shipping_preference' => PayPalOrder::PAYPAL_ADDRESS,
            'contact_preference' => PayPalOrder::UPDATE_CONTACT_INFO,
            'user_action' => PayPalOrder::USER_ACTION_PAY_NOW,
            'payment_method_preference' => PayPalOrder::PAYMENT_METHOD_PREFERENCE_IMMEDIATE,
            'app_switch_preference' => ['launch_paypal_app' => true],
        ];

        $payPalOrder = new PayPalOrder($this->payPalPurchaseUnit, PayPalOrder::INTENT_CAPTURE, $experienceContext);

        self::assertEquals([
            'intent' => 'CAPTURE',
            'purchase_units' => [
                ['reference_id' => 'REFERENCE_ID'],
            ],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => $experienceContext,
                ],
            ],
        ], $payPalOrder->toArray());
    }

    #[Test]
    public function it_keeps_the_legacy_application_context_when_the_address_is_already_provided(): void
    {
        $this->payPalPurchaseUnit->method('toArray')->willReturn(['reference_id' => 'REFERENCE_ID']);

        $payPalOrder = new PayPalOrder(
            $this->payPalPurchaseUnit,
            PayPalOrder::INTENT_CAPTURE,
            ['shipping_preference' => PayPalOrder::PROVIDED_ADDRESS, 'app_switch_preference' => ['launch_paypal_app' => true]],
        );

        $result = $payPalOrder->toArray();

        self::assertArrayNotHasKey('payment_source', $result);
        self::assertSame(
            ['shipping_preference' => 'SET_PROVIDED_ADDRESS', 'user_action' => 'PAY_NOW'],
            $result['application_context'],
        );
    }

    #[Test]
    public function it_keeps_the_legacy_application_context_when_shipping_is_not_required(): void
    {
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $payPalOrder = new PayPalOrder(
            $this->payPalPurchaseUnit,
            PayPalOrder::INTENT_CAPTURE,
            ['shipping_preference' => PayPalOrder::NO_SHIPPING],
        );

        $result = $payPalOrder->toArray();

        self::assertArrayNotHasKey('payment_source', $result);
        self::assertSame(
            ['shipping_preference' => 'NO_SHIPPING', 'user_action' => 'PAY_NOW'],
            $result['application_context'],
        );
    }

    #[Test]
    public function it_never_sends_both_context_blocks_at_once(): void
    {
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $payPalOrder = new PayPalOrder(
            $this->payPalPurchaseUnit,
            PayPalOrder::INTENT_CAPTURE,
            ['shipping_preference' => PayPalOrder::PAYPAL_ADDRESS],
        );

        $result = $payPalOrder->toArray();

        self::assertArrayHasKey('payment_source', $result);
        self::assertArrayNotHasKey('application_context', $result);
    }

    #[Test]
    public function it_falls_back_to_a_no_shipping_application_context_without_an_experience_context(): void
    {
        $this->payPalPurchaseUnit->method('toArray')->willReturn([]);

        $payPalOrder = new PayPalOrder($this->payPalPurchaseUnit, PayPalOrder::INTENT_CAPTURE);

        $result = $payPalOrder->toArray();

        self::assertArrayNotHasKey('payment_source', $result);
        self::assertSame(
            ['shipping_preference' => 'NO_SHIPPING', 'user_action' => 'PAY_NOW'],
            $result['application_context'],
        );
    }
}
