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

namespace Tests\Sylius\PayPalPlugin\Unit\Controller;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\TokenAssigner\OrderTokenAssignerInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\PayPalPlugin\Controller\PayPalButtonsController;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalWebSdkConfigurationProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class PayPalButtonsControllerTest extends TestCase
{
    private Environment&MockObject $twig;

    private UrlGeneratorInterface&MockObject $router;

    private ChannelContextInterface&MockObject $channelContext;

    private LocaleContextInterface&MockObject $localeContext;

    private PayPalConfigurationProviderInterface&MockObject $payPalConfigurationProvider;

    /** @var OrderRepositoryInterface<OrderInterface>&MockObject */
    private OrderRepositoryInterface&MockObject $orderRepository;

    private AvailableCountriesProviderInterface&MockObject $availableCountriesProvider;

    private LocaleProcessorInterface&MockObject $localeProcessor;

    private PayPalWebSdkConfigurationProviderInterface&MockObject $webSdkConfigurationProvider;

    private OrderTokenAssignerInterface&MockObject $orderTokenAssigner;

    private ObjectManager&MockObject $orderManager;

    private ChannelInterface&MockObject $channel;

    private PayPalButtonsController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->twig = $this->createMock(Environment::class);
        $this->router = $this->createMock(UrlGeneratorInterface::class);
        $this->channelContext = $this->createMock(ChannelContextInterface::class);
        $this->localeContext = $this->createMock(LocaleContextInterface::class);
        $this->payPalConfigurationProvider = $this->createMock(PayPalConfigurationProviderInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->availableCountriesProvider = $this->createMock(AvailableCountriesProviderInterface::class);
        $this->localeProcessor = $this->createMock(LocaleProcessorInterface::class);
        $this->webSdkConfigurationProvider = $this->createMock(PayPalWebSdkConfigurationProviderInterface::class);
        $this->orderTokenAssigner = $this->createMock(OrderTokenAssignerInterface::class);
        $this->orderManager = $this->createMock(ObjectManager::class);

        $this->channel = $this->createMock(ChannelInterface::class);
        $this->channelContext->method('getChannel')->willReturn($this->channel);
        $this->localeContext->method('getLocaleCode')->willReturn('en_US');
        $this->localeProcessor->method('process')->willReturnArgument(0);
        $this->availableCountriesProvider->method('provide')->willReturn([]);
        $this->router->method('generate')->willReturn('/some-url');
        $this->webSdkConfigurationProvider->method('getScriptUrl')->willReturn('https://www.paypal.com/web-sdk/v6/core');
        $this->webSdkConfigurationProvider->method('getInstanceConfig')->willReturn(['clientId' => 'CLIENT_ID']);
        $this->twig->method('render')->willReturn('');

        $this->controller = new PayPalButtonsController(
            $this->twig,
            $this->router,
            $this->channelContext,
            $this->localeContext,
            $this->payPalConfigurationProvider,
            $this->orderRepository,
            $this->availableCountriesProvider,
            $this->localeProcessor,
            $this->webSdkConfigurationProvider,
            $this->orderTokenAssigner,
            $this->orderManager,
        );
    }

    /** @test */
    public function it_assigns_and_persists_a_token_when_the_cart_page_order_has_none(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getTokenValue')->willReturn(null);
        $this->orderRepository->method('find')->willReturn($order);

        $this->orderTokenAssigner->expects(self::once())->method('assignTokenValueIfNotSet')->with($order);
        $this->orderManager->expects(self::once())->method('flush');

        $this->controller->renderCartPageButtonsAction(Request::create('/', 'GET', ['orderId' => 1]));
    }

    /** @test */
    public function it_does_not_flush_when_the_cart_page_order_already_has_a_token(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getTokenValue')->willReturn('EXISTING_TOKEN');
        $this->orderRepository->method('find')->willReturn($order);

        $this->orderTokenAssigner->expects(self::never())->method('assignTokenValueIfNotSet');
        $this->orderManager->expects(self::never())->method('flush');

        $this->controller->renderCartPageButtonsAction(Request::create('/', 'GET', ['orderId' => 1]));
    }

    /** @test */
    public function it_assigns_and_persists_a_token_when_the_payment_page_order_has_none(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getTokenValue')->willReturn(null);
        $this->orderRepository->method('find')->willReturn($order);

        $this->orderTokenAssigner->expects(self::once())->method('assignTokenValueIfNotSet')->with($order);
        $this->orderManager->expects(self::once())->method('flush');

        $this->controller->renderPaymentPageButtonsAction(Request::create('/', 'GET', ['orderId' => 1]));
    }

    /** @test */
    public function it_does_not_flush_when_the_payment_page_order_already_has_a_token(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getTokenValue')->willReturn('EXISTING_TOKEN');
        $this->orderRepository->method('find')->willReturn($order);

        $this->orderTokenAssigner->expects(self::never())->method('assignTokenValueIfNotSet');
        $this->orderManager->expects(self::never())->method('flush');

        $this->controller->renderPaymentPageButtonsAction(Request::create('/', 'GET', ['orderId' => 1]));
    }
}
