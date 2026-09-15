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

namespace Tests\Sylius\PayPalPlugin\Unit\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Provider\ExperienceContextProvider;

final class ExperienceContextProviderTest extends TestCase
{
    private ExperienceContextProvider $provider;

    private OrderInterface&MockObject $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new ExperienceContextProvider();
        $this->order = $this->createMock(OrderInterface::class);
        $this->order->method('getLocaleCode')->willReturn('en_US');
    }

    #[Test]
    public function it_builds_the_enriched_experience_context_for_a_wallet_addressed_order(): void
    {
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);

        $experienceContext = $this->provider->provide(
            $this->order,
            'https://shop.example.com/checkout/complete',
            'https://shop.example.com/checkout/complete',
            'https://shop.example.com/paypal/order-shipping-callback',
        );

        self::assertSame([
            'locale' => 'en-US',
            'shipping_preference' => 'GET_FROM_FILE',
            'contact_preference' => 'UPDATE_CONTACT_INFO',
            'user_action' => 'PAY_NOW',
            'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
            'return_url' => 'https://shop.example.com/checkout/complete',
            'cancel_url' => 'https://shop.example.com/checkout/complete',
            'app_switch_preference' => ['launch_paypal_app' => true],
            'order_update_callback_config' => [
                'callback_events' => ['SHIPPING_ADDRESS'],
                'callback_url' => 'https://shop.example.com/paypal/order-shipping-callback',
            ],
        ], $experienceContext);
    }

    #[Test]
    public function it_omits_the_shipping_callback_when_the_address_is_already_provided(): void
    {
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn($this->createMock(AddressInterface::class));

        $experienceContext = $this->provider->provide(
            $this->order,
            'https://shop.example.com/checkout/complete',
            'https://shop.example.com/checkout/complete',
            'https://shop.example.com/paypal/order-shipping-callback',
        );

        self::assertSame('SET_PROVIDED_ADDRESS', $experienceContext['shipping_preference']);
        self::assertArrayNotHasKey('order_update_callback_config', $experienceContext);
    }

    #[Test]
    public function it_omits_the_shipping_callback_when_shipping_is_not_required(): void
    {
        $this->order->method('isShippingRequired')->willReturn(false);

        $experienceContext = $this->provider->provide(
            $this->order,
            'https://shop.example.com/checkout/complete',
            'https://shop.example.com/checkout/complete',
            'https://shop.example.com/paypal/order-shipping-callback',
        );

        self::assertSame('NO_SHIPPING', $experienceContext['shipping_preference']);
        self::assertArrayNotHasKey('order_update_callback_config', $experienceContext);
    }

    #[Test]
    public function it_omits_the_brand_name_by_default(): void
    {
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);

        self::assertArrayNotHasKey('brand_name', $this->provider->provide($this->order));
    }

    #[Test]
    public function it_normalises_the_order_locale_to_bcp_47(): void
    {
        $this->order->method('isShippingRequired')->willReturn(false);

        self::assertSame('en-US', $this->provider->provide($this->order)['locale']);
    }

    #[Test]
    public function it_omits_the_urls_and_callback_when_they_are_not_given(): void
    {
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);

        $experienceContext = $this->provider->provide($this->order);

        self::assertArrayNotHasKey('return_url', $experienceContext);
        self::assertArrayNotHasKey('cancel_url', $experienceContext);
        self::assertArrayNotHasKey('order_update_callback_config', $experienceContext);
    }

    #[Test]
    public function it_marks_the_contact_info_as_retained_when_the_order_carries_an_address(): void
    {
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn($this->createMock(AddressInterface::class));

        $experienceContext = $this->provider->provide($this->order);

        self::assertSame('SET_PROVIDED_ADDRESS', $experienceContext['shipping_preference']);
        self::assertSame('RETAIN_CONTACT_INFO', $experienceContext['contact_preference']);
    }

    #[Test]
    public function it_marks_a_non_shippable_order_as_no_shipping(): void
    {
        $this->order->method('isShippingRequired')->willReturn(false);

        self::assertSame('NO_SHIPPING', $this->provider->provide($this->order)['shipping_preference']);
    }
}
