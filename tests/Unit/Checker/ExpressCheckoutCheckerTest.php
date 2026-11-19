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

namespace Tests\Sylius\PayPalPlugin\Unit\Checker;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Checker\ExpressCheckoutChecker;
use Sylius\PayPalPlugin\Provider\PayPalFundingSourcesConfigurationProviderInterface;

final class ExpressCheckoutCheckerTest extends TestCase
{
    public function test_it_tells_whether_express_checkout_is_enabled_on_the_channel_of_the_order(): void
    {
        $channel = $this->createStub(ChannelInterface::class);
        $provider = $this->createMock(PayPalFundingSourcesConfigurationProviderInterface::class);
        $provider->expects(self::exactly(2))->method('isExpressCheckoutEnabled')->with($channel)->willReturnOnConsecutiveCalls(true, false);
        $checker = new ExpressCheckoutChecker($provider);

        self::assertTrue($checker->isEnabled($this->order($channel)));
        self::assertFalse($checker->isEnabled($this->order($channel)));
    }

    public function test_it_treats_express_checkout_as_enabled_when_the_channel_has_no_paypal_payment_method(): void
    {
        $provider = $this->createStub(PayPalFundingSourcesConfigurationProviderInterface::class);
        $provider->method('isExpressCheckoutEnabled')->willThrowException(new \InvalidArgumentException());

        self::assertTrue((new ExpressCheckoutChecker($provider))->isEnabled($this->order($this->createStub(ChannelInterface::class))));
    }

    public function test_it_treats_express_checkout_as_enabled_without_a_channel_or_a_provider(): void
    {
        $provider = $this->createMock(PayPalFundingSourcesConfigurationProviderInterface::class);
        $provider->expects(self::never())->method('isExpressCheckoutEnabled');

        self::assertTrue((new ExpressCheckoutChecker($provider))->isEnabled($this->order(null)));
        self::assertTrue((new ExpressCheckoutChecker(null))->isEnabled($this->order($this->createStub(ChannelInterface::class))));
    }

    private function order(?ChannelInterface $channel): OrderInterface
    {
        $order = $this->createStub(OrderInterface::class);
        $order->method('getChannel')->willReturn($channel);

        return $order;
    }
}
