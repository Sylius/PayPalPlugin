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
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Locale\Context\LocaleNotFoundException;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
use Sylius\PayPalPlugin\Provider\PayPalFundingSourcesConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalWebSdkConfigurationProviderInterface;
use Sylius\PayPalPlugin\Twig\PayPalExtension;

final class PayPalExtensionTest extends TestCase
{
    private PayPalFundingSourcesConfigurationProviderInterface&MockObject $fundingSourcesConfigurationProvider;

    private ChannelContextInterface&MockObject $channelContext;

    private PayPalWebSdkConfigurationProviderInterface&MockObject $webSdkConfigurationProvider;

    private LocaleContextInterface&MockObject $localeContext;

    private LocaleProcessorInterface&MockObject $localeProcessor;

    private PayPalExtension $extension;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fundingSourcesConfigurationProvider = $this->createMock(PayPalFundingSourcesConfigurationProviderInterface::class);
        $this->channelContext = $this->createMock(ChannelContextInterface::class);
        $this->webSdkConfigurationProvider = $this->createMock(PayPalWebSdkConfigurationProviderInterface::class);
        $this->localeContext = $this->createMock(LocaleContextInterface::class);
        $this->localeProcessor = $this->createMock(LocaleProcessorInterface::class);
        $this->extension = new PayPalExtension(
            true,
            $this->fundingSourcesConfigurationProvider,
            $this->channelContext,
            $this->webSdkConfigurationProvider,
            localeContext: $this->localeContext,
            localeProcessor: $this->localeProcessor,
        );
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

    #[Test]
    public function it_returns_false_when_constructed_without_the_new_dependencies(): void
    {
        $extension = new PayPalExtension(true);

        self::assertFalse($extension->isMessagingEnabled());
    }

    #[Test]
    public function it_returns_the_web_sdk_script_url(): void
    {
        $this->webSdkConfigurationProvider->method('getScriptUrl')->willReturn('https://www.sandbox.paypal.com/web-sdk/v6/core');

        self::assertSame('https://www.sandbox.paypal.com/web-sdk/v6/core', $this->extension->getWebSdkScriptUrl());
    }

    #[Test]
    public function it_returns_an_empty_script_url_when_constructed_without_the_new_dependency(): void
    {
        $extension = new PayPalExtension(true);

        self::assertSame('', $extension->getWebSdkScriptUrl());
    }

    #[Test]
    public function it_returns_the_web_sdk_instance_config_for_the_current_channel(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->webSdkConfigurationProvider
            ->method('getInstanceConfig')
            ->with($channel, 'cart')
            ->willReturn(['clientId' => 'CLIENT_ID', 'components' => ['paypal-payments']]);

        self::assertSame(
            ['clientId' => 'CLIENT_ID', 'components' => ['paypal-payments']],
            $this->extension->getWebSdkInstanceConfig('cart'),
        );
    }

    #[Test]
    public function it_forwards_the_given_locale_to_the_web_sdk_instance_config(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->webSdkConfigurationProvider
            ->method('getInstanceConfig')
            ->with($channel, 'checkout', ['paypal-messages'], 'en_US')
            ->willReturn(['clientId' => 'CLIENT_ID']);

        self::assertSame(
            ['clientId' => 'CLIENT_ID'],
            $this->extension->getWebSdkInstanceConfig('checkout', 'en_US'),
        );
    }

    #[Test]
    public function it_resolves_the_current_locale_when_none_is_given(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->localeContext->method('getLocaleCode')->willReturn('pl_PL');
        $this->localeProcessor->method('process')->with('pl_PL')->willReturn('pl_PL');
        $this->webSdkConfigurationProvider
            ->method('getInstanceConfig')
            ->with($channel, 'cart', ['paypal-messages'], 'pl_PL')
            ->willReturn(['clientId' => 'CLIENT_ID', 'locale' => 'pl-PL']);

        self::assertSame(
            ['clientId' => 'CLIENT_ID', 'locale' => 'pl-PL'],
            $this->extension->getWebSdkInstanceConfig('cart'),
        );
    }

    #[Test]
    public function it_does_not_resolve_the_current_locale_when_one_is_explicitly_given(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->localeProcessor->expects(self::never())->method('process');
        $this->webSdkConfigurationProvider->method('getInstanceConfig')->willReturn([]);

        $this->extension->getWebSdkInstanceConfig('checkout', 'en_US');
    }

    #[Test]
    public function it_renders_without_a_locale_when_the_current_one_cannot_be_resolved(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->localeContext->method('getLocaleCode')->willThrowException(new LocaleNotFoundException());
        $this->webSdkConfigurationProvider
            ->method('getInstanceConfig')
            ->with($channel, 'cart', ['paypal-messages'], null)
            ->willReturn(['clientId' => 'CLIENT_ID']);

        self::assertSame(
            ['clientId' => 'CLIENT_ID'],
            $this->extension->getWebSdkInstanceConfig('cart'),
        );
    }

    #[Test]
    public function it_returns_an_empty_instance_config_when_no_pay_pal_payment_method_is_configured_for_the_channel(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $this->channelContext->method('getChannel')->willReturn($channel);
        $this->webSdkConfigurationProvider
            ->method('getInstanceConfig')
            ->willThrowException(new \InvalidArgumentException('No PayPal payment method defined'));

        self::assertSame([], $this->extension->getWebSdkInstanceConfig('cart'));
    }

    #[Test]
    public function it_returns_an_empty_instance_config_when_constructed_without_the_new_dependencies(): void
    {
        $extension = new PayPalExtension(true);

        self::assertSame([], $extension->getWebSdkInstanceConfig('cart'));
    }

    public function test_it_tells_a_template_when_the_payer_is_still_finishing_off_site(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_PROCESSING);
        $payment->method('getDetails')->willReturn([
            'payment_source' => 'trustly',
            'payer_action_url' => 'https://www.paypal.com/payment/trustly?token=X',
        ]);

        self::assertTrue($this->extension->isAwaitingPayerAction($payment));
    }

    public function test_it_tells_a_template_a_wallet_payment_is_not_waiting_on_the_payer(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_PROCESSING);
        $payment->method('getDetails')->willReturn(['payment_source' => 'paypal']);

        self::assertFalse($this->extension->isAwaitingPayerAction($payment));
    }
}
