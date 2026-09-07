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

namespace Tests\Sylius\PayPalPlugin\Unit\Twig;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\PayPalPlugin\Provider\PayPalFundingSourcesConfigurationProviderInterface;
use Sylius\PayPalPlugin\Twig\PayPalExtension;

final class PayPalExtensionTest extends TestCase
{
    private PayPalFundingSourcesConfigurationProviderInterface&MockObject $fundingSourcesConfigurationProvider;

    private ChannelContextInterface&MockObject $channelContext;

    private PayPalExtension $extension;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fundingSourcesConfigurationProvider = $this->createMock(PayPalFundingSourcesConfigurationProviderInterface::class);
        $this->channelContext = $this->createMock(ChannelContextInterface::class);
        $this->extension = new PayPalExtension(true, $this->fundingSourcesConfigurationProvider, $this->channelContext);
    }

    #[Test]
    public function it_returns_whether_messaging_is_enabled_for_the_current_channel(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->fundingSourcesConfigurationProvider->method('isMessagingEnabled')->with($channel)->willReturn(true);

        self::assertTrue($this->extension->isMessagingEnabled());
    }

    #[Test]
    public function it_returns_false_when_no_pay_pal_payment_method_is_configured_for_the_channel(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->fundingSourcesConfigurationProvider
            ->method('isMessagingEnabled')
            ->willThrowException(new \InvalidArgumentException('No PayPal payment method defined'));

        self::assertFalse($this->extension->isMessagingEnabled());
    }
}
