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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Provider\ShippingCallbackUrlProvider;
use Sylius\PayPalPlugin\Provider\ShippingCallbackUrlProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ShippingCallbackUrlProviderTest extends TestCase
{
    private UrlGeneratorInterface&MockObject $router;

    private ShippingCallbackUrlProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = $this->createMock(UrlGeneratorInterface::class);

        $this->provider = new ShippingCallbackUrlProvider($this->router);
    }

    public function test_it_implements_shipping_callback_url_provider_interface(): void
    {
        self::assertInstanceOf(ShippingCallbackUrlProviderInterface::class, $this->provider);
    }

    public function test_it_generates_the_shipping_callback_route_as_an_absolute_url(): void
    {
        $this->router
            ->expects(self::once())
            ->method('generate')
            ->with('sylius_paypal_order_shipping_callback', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://shop.example.com/paypal/order-shipping-callback')
        ;

        self::assertSame(
            'https://shop.example.com/paypal/order-shipping-callback',
            $this->provider->provide(),
        );
    }

    public function test_it_provides_no_url_paypal_could_not_reach(): void
    {
        $this->router->method('generate')->willReturn('http://shop.example.com/paypal/order-shipping-callback');

        self::assertNull($this->provider->provide());
    }

    public function test_it_generates_the_route_it_is_given(): void
    {
        $this->router
            ->expects(self::once())
            ->method('generate')
            ->with('other_route', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://shop.example.com/other')
        ;

        self::assertSame('https://shop.example.com/other', (new ShippingCallbackUrlProvider(
            $this->router,
            'other_route',
        ))->provide());
    }
}
