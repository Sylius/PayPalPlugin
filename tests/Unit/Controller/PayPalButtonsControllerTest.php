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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\PayPalPlugin\Controller\PayPalButtonsController;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalFundingSourcesConfigurationProviderInterface;
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

    private PayPalFundingSourcesConfigurationProviderInterface&MockObject $fundingSourcesConfigurationProvider;

    private ChannelInterface&MockObject $channel;

    private PayPalButtonsController $controller;

    /** @var array<int, mixed>|null */
    private ?array $capturedInstanceConfigArgs = null;

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
        $this->fundingSourcesConfigurationProvider = $this->createMock(PayPalFundingSourcesConfigurationProviderInterface::class);

        $this->channel = $this->createMock(ChannelInterface::class);
        $this->channelContext->method('getChannel')->willReturn($this->channel);
        $this->localeContext->method('getLocaleCode')->willReturn('pl_PL');
        $this->localeProcessor->method('process')->willReturnArgument(0);
        $this->availableCountriesProvider->method('provide')->willReturn([]);
        $this->router->method('generate')->willReturn('/some-url');
        $this->webSdkConfigurationProvider->method('getScriptUrl')->willReturn('https://www.paypal.com/web-sdk/v6/core');
        $this->webSdkConfigurationProvider
            ->method('getInstanceConfig')
            ->willReturnCallback(function (...$arguments): array {
                $this->capturedInstanceConfigArgs = $arguments;

                return ['clientId' => 'CLIENT_ID'];
            });

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
            $this->fundingSourcesConfigurationProvider,
        );
    }

    #[Test]
    public function it_passes_paylater_enabled_to_the_product_page_template(): void
    {
        $this->fundingSourcesConfigurationProvider->method('isPayLaterEnabled')->with($this->channel)->willReturn(true);
        $this->fundingSourcesConfigurationProvider->method('isVenmoEnabled')->with($this->channel)->willReturn(false);

        $capturedContext = null;
        $this->twig->method('render')
            ->with('@SyliusPayPalPlugin/pay_from_product_page.html.twig', self::isType('array'))
            ->willReturnCallback(function (string $template, array $context) use (&$capturedContext): string {
                $capturedContext = $context;

                return '';
            });

        $this->controller->renderProductPageButtonsAction(Request::create('/'));

        self::assertTrue($capturedContext['paylaterEnabled']);
        self::assertFalse($capturedContext['venmoEnabled']);
    }

    #[Test]
    public function it_passes_paylater_enabled_to_the_cart_page_template(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getTotal')->willReturn(3050);
        $this->orderRepository->method('find')->willReturn($order);
        $this->fundingSourcesConfigurationProvider->method('isPayLaterEnabled')->with($this->channel)->willReturn(false);
        $this->fundingSourcesConfigurationProvider->method('isVenmoEnabled')->with($this->channel)->willReturn(true);

        $capturedContext = null;
        $this->twig->method('render')
            ->with('@SyliusPayPalPlugin/pay_from_cart_page.html.twig', self::isType('array'))
            ->willReturnCallback(function (string $template, array $context) use (&$capturedContext): string {
                $capturedContext = $context;

                return '';
            });

        $this->controller->renderCartPageButtonsAction(Request::create('/', 'GET', ['orderId' => 1]));

        self::assertFalse($capturedContext['paylaterEnabled']);
        self::assertSame('30.50', $capturedContext['amount']);
        self::assertTrue($capturedContext['venmoEnabled']);
    }

    #[Test]
    public function it_passes_paylater_enabled_to_the_payment_page_template(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getTotal')->willReturn(3000);
        $this->orderRepository->method('find')->willReturn($order);
        $this->fundingSourcesConfigurationProvider->method('isPayLaterEnabled')->with($this->channel)->willReturn(true);
        $this->fundingSourcesConfigurationProvider->method('isVenmoEnabled')->with($this->channel)->willReturn(true);

        $capturedContext = null;
        $this->twig->method('render')
            ->with('@SyliusPayPalPlugin/pay_from_payment_page.html.twig', self::isType('array'))
            ->willReturnCallback(function (string $template, array $context) use (&$capturedContext): string {
                $capturedContext = $context;

                return '';
            });

        $this->controller->renderPaymentPageButtonsAction(Request::create('/', 'GET', ['orderId' => 1]));

        self::assertTrue($capturedContext['paylaterEnabled']);
        self::assertSame('30.00', $capturedContext['amount']);
        self::assertTrue($capturedContext['venmoEnabled']);
    }

    #[Test]
    public function it_passes_venmo_enabled_to_the_product_page_venmo_template(): void
    {
        $this->fundingSourcesConfigurationProvider->method('isVenmoEnabled')->with($this->channel)->willReturn(true);

        $capturedContext = null;
        $this->twig->method('render')
            ->with('@SyliusPayPalPlugin/pay_from_product_page_venmo.html.twig', self::isType('array'))
            ->willReturnCallback(function (string $template, array $context) use (&$capturedContext): string {
                $capturedContext = $context;

                return '';
            });

        $this->controller->renderProductPageVenmoButtonAction(Request::create('/'));

        self::assertTrue($capturedContext['venmoEnabled']);
    }

    #[Test]
    public function it_passes_venmo_enabled_to_the_cart_page_venmo_template(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getTotal')->willReturn(3050);
        $this->orderRepository->method('find')->willReturn($order);
        $this->fundingSourcesConfigurationProvider->method('isVenmoEnabled')->with($this->channel)->willReturn(true);

        $capturedContext = null;
        $this->twig->method('render')
            ->with('@SyliusPayPalPlugin/pay_from_cart_page_venmo.html.twig', self::isType('array'))
            ->willReturnCallback(function (string $template, array $context) use (&$capturedContext): string {
                $capturedContext = $context;

                return '';
            });

        $this->controller->renderCartPageVenmoButtonAction(Request::create('/', 'GET', ['orderId' => 1]));

        self::assertSame('30.50', $capturedContext['amount']);
        self::assertTrue($capturedContext['venmoEnabled']);
    }

    #[Test]
    public function it_passes_venmo_enabled_to_the_payment_page_venmo_template(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getTotal')->willReturn(3000);
        $this->orderRepository->method('find')->willReturn($order);
        $this->fundingSourcesConfigurationProvider->method('isVenmoEnabled')->with($this->channel)->willReturn(true);

        $capturedContext = null;
        $this->twig->method('render')
            ->with('@SyliusPayPalPlugin/pay_from_payment_page_venmo.html.twig', self::isType('array'))
            ->willReturnCallback(function (string $template, array $context) use (&$capturedContext): string {
                $capturedContext = $context;

                return '';
            });

        $this->controller->renderPaymentPageVenmoButtonAction(Request::create('/', 'GET', ['orderId' => 1]));

        self::assertSame('30.00', $capturedContext['amount']);
        self::assertTrue($capturedContext['venmoEnabled']);
    }

    #[Test]
    public function it_requests_the_venmo_payments_component_on_the_venmo_only_product_action(): void
    {
        $this->fundingSourcesConfigurationProvider->method('isVenmoEnabled')->with($this->channel)->willReturn(true);

        $this->webSdkConfigurationProvider
            ->expects(self::once())
            ->method('getInstanceConfig')
            ->with($this->channel, 'product-details', ['paypal-payments', 'venmo-payments'], 'pl_PL')
            ->willReturn(['clientId' => 'CLIENT_ID']);

        $this->controller->renderProductPageVenmoButtonAction(Request::create('/'));
    }

    #[Test]
    public function it_requests_the_venmo_payments_component_on_the_venmo_only_cart_action(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getTotal')->willReturn(3050);
        $this->orderRepository->method('find')->willReturn($order);
        $this->fundingSourcesConfigurationProvider->method('isVenmoEnabled')->with($this->channel)->willReturn(true);

        $this->webSdkConfigurationProvider
            ->expects(self::once())
            ->method('getInstanceConfig')
            ->with($this->channel, 'cart', ['paypal-payments', 'venmo-payments'], '')
            ->willReturn(['clientId' => 'CLIENT_ID']);

        $this->controller->renderCartPageVenmoButtonAction(Request::create('/', 'GET', ['orderId' => 1]));
    }

    #[Test]
    public function it_requests_the_venmo_payments_component_on_the_venmo_only_payment_page_action(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getTotal')->willReturn(3000);
        $this->orderRepository->method('find')->willReturn($order);
        $this->fundingSourcesConfigurationProvider->method('isVenmoEnabled')->with($this->channel)->willReturn(true);

        $this->webSdkConfigurationProvider
            ->expects(self::once())
            ->method('getInstanceConfig')
            ->with($this->channel, 'checkout', ['paypal-payments', 'venmo-payments'], '')
            ->willReturn(['clientId' => 'CLIENT_ID']);

        $this->controller->renderPaymentPageVenmoButtonAction(Request::create('/', 'GET', ['orderId' => 1]));
    }

    #[Test]
    public function it_passes_the_locale_to_the_web_sdk_instance_config_on_the_product_page_venmo_action(): void
    {
        $this->twig->method('render')->willReturn('');

        $this->controller->renderProductPageVenmoButtonAction(Request::create('/'));

        self::assertSame('product-details', $this->capturedInstanceConfigArgs[1]);
        self::assertSame('pl_PL', $this->capturedInstanceConfigArgs[3]);
    }

    #[Test]
    public function it_passes_the_order_locale_to_the_web_sdk_instance_config_on_the_cart_page_venmo_action(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('PLN');
        $order->method('getTotal')->willReturn(3050);
        $order->method('getLocaleCode')->willReturn('pl_PL');
        $this->orderRepository->method('find')->willReturn($order);

        $this->controller->renderCartPageVenmoButtonAction(Request::create('/', 'GET', ['orderId' => 1]));

        self::assertSame('cart', $this->capturedInstanceConfigArgs[1]);
        self::assertSame('pl_PL', $this->capturedInstanceConfigArgs[3]);
    }

    #[Test]
    public function it_passes_the_order_locale_to_the_web_sdk_instance_config_on_the_payment_page_venmo_action(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('PLN');
        $order->method('getTotal')->willReturn(3000);
        $order->method('getLocaleCode')->willReturn('pl_PL');
        $this->orderRepository->method('find')->willReturn($order);

        $this->controller->renderPaymentPageVenmoButtonAction(Request::create('/', 'GET', ['orderId' => 1]));

        self::assertSame('checkout', $this->capturedInstanceConfigArgs[1]);
        self::assertSame('pl_PL', $this->capturedInstanceConfigArgs[3]);
    }

    #[Test]
    public function it_returns_an_empty_response_from_the_venmo_only_product_action_when_no_pay_pal_payment_method_is_configured(): void
    {
        $this->payPalConfigurationProvider
            ->method('getClientId')
            ->willThrowException(new \InvalidArgumentException('No PayPal payment method defined'));

        $response = $this->controller->renderProductPageVenmoButtonAction(Request::create('/'));

        self::assertSame('', $response->getContent());
    }

    #[Test]
    public function it_returns_an_empty_response_from_the_venmo_only_cart_action_when_no_pay_pal_payment_method_is_configured(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getTotal')->willReturn(3050);
        $this->orderRepository->method('find')->willReturn($order);
        $this->webSdkConfigurationProvider
            ->method('getInstanceConfig')
            ->willThrowException(new \InvalidArgumentException('No PayPal payment method defined'));

        $response = $this->controller->renderCartPageVenmoButtonAction(Request::create('/', 'GET', ['orderId' => 1]));

        self::assertSame('', $response->getContent());
    }

    #[Test]
    public function it_returns_an_empty_response_from_the_venmo_only_payment_page_action_when_no_pay_pal_payment_method_is_configured(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getTotal')->willReturn(3000);
        $this->orderRepository->method('find')->willReturn($order);
        $this->webSdkConfigurationProvider
            ->method('getInstanceConfig')
            ->willThrowException(new \InvalidArgumentException('No PayPal payment method defined'));

        $response = $this->controller->renderPaymentPageVenmoButtonAction(Request::create('/', 'GET', ['orderId' => 1]));

        self::assertSame('', $response->getContent());
    }

    #[Test]
    public function it_requests_the_venmo_payments_component_when_venmo_is_enabled(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getTotal')->willReturn(3050);
        $this->orderRepository->method('find')->willReturn($order);
        $this->fundingSourcesConfigurationProvider->method('isPayLaterEnabled')->willReturn(false);
        $this->fundingSourcesConfigurationProvider->method('isVenmoEnabled')->with($this->channel)->willReturn(true);

        $this->webSdkConfigurationProvider
            ->expects(self::once())
            ->method('getInstanceConfig')
            ->with($this->channel, 'cart', ['paypal-payments', 'venmo-payments'], '')
            ->willReturn(['clientId' => 'CLIENT_ID']);

        $this->controller->renderCartPageButtonsAction(Request::create('/', 'GET', ['orderId' => 1]));
    }

    #[Test]
    public function it_does_not_request_the_venmo_payments_component_when_venmo_is_disabled(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getTotal')->willReturn(3050);
        $this->orderRepository->method('find')->willReturn($order);
        $this->fundingSourcesConfigurationProvider->method('isPayLaterEnabled')->willReturn(false);
        $this->fundingSourcesConfigurationProvider->method('isVenmoEnabled')->with($this->channel)->willReturn(false);

        $this->webSdkConfigurationProvider
            ->expects(self::once())
            ->method('getInstanceConfig')
            ->with($this->channel, 'cart', ['paypal-payments'], '')
            ->willReturn(['clientId' => 'CLIENT_ID']);

        $this->controller->renderCartPageButtonsAction(Request::create('/', 'GET', ['orderId' => 1]));
    }

    #[Test]
    public function it_passes_the_locale_to_the_web_sdk_instance_config_on_the_product_page(): void
    {
        $this->twig->method('render')->willReturn('');

        $this->controller->renderProductPageButtonsAction(Request::create('/'));

        self::assertSame('product-details', $this->capturedInstanceConfigArgs[1]);
        self::assertSame('pl_PL', $this->capturedInstanceConfigArgs[3]);
    }

    #[Test]
    public function it_passes_the_order_locale_to_the_web_sdk_instance_config_on_the_cart_page(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('PLN');
        $order->method('getTotal')->willReturn(3050);
        $order->method('getLocaleCode')->willReturn('pl_PL');
        $this->orderRepository->method('find')->willReturn($order);
        $this->twig->method('render')->willReturn('');

        $this->controller->renderCartPageButtonsAction(Request::create('/', 'GET', ['orderId' => 1]));

        self::assertSame('cart', $this->capturedInstanceConfigArgs[1]);
        self::assertSame('pl_PL', $this->capturedInstanceConfigArgs[3]);
    }

    #[Test]
    public function it_passes_the_order_locale_to_the_web_sdk_instance_config_on_the_payment_page(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('PLN');
        $order->method('getTotal')->willReturn(3000);
        $order->method('getLocaleCode')->willReturn('pl_PL');
        $this->orderRepository->method('find')->willReturn($order);
        $this->twig->method('render')->willReturn('');

        $this->controller->renderPaymentPageButtonsAction(Request::create('/', 'GET', ['orderId' => 1]));

        self::assertSame('checkout', $this->capturedInstanceConfigArgs[1]);
        self::assertSame('pl_PL', $this->capturedInstanceConfigArgs[3]);
    }

    #[Test]
    public function it_returns_an_empty_response_when_no_pay_pal_payment_method_is_configured(): void
    {
        $this->payPalConfigurationProvider
            ->method('getClientId')
            ->willThrowException(new \InvalidArgumentException('No PayPal payment method defined'));

        $response = $this->controller->renderProductPageButtonsAction(Request::create('/'));

        self::assertSame('', $response->getContent());
    }
}
