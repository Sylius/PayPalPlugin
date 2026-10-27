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

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Provider\WebhookUrlProvider;
use Sylius\PayPalPlugin\Provider\WebhookUrlProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

final class WebhookUrlProviderTest extends TestCase
{
    private UrlGeneratorInterface&MockObject $urlGenerator;

    private RequestStack $console;

    private RequestStack $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $parameters, int $referenceType): string {
                $path = '/payment-methods/' . $parameters['code'];

                return UrlGeneratorInterface::ABSOLUTE_URL === $referenceType ? 'https://admin.example.com' . $path : $path;
            },
        );
        $this->urlGenerator->method('getContext')->willReturn(new RequestContext(host: 'admin.example.com', scheme: 'https'));
        $this->console = new RequestStack();
        $this->request = new RequestStack();
        $this->request->push(Request::create('https://admin.example.com/admin'));
    }

    public function test_it_implements_webhook_url_provider_interface(): void
    {
        self::assertInstanceOf(WebhookUrlProviderInterface::class, new WebhookUrlProvider($this->urlGenerator, $this->console));
    }

    public function test_it_takes_the_host_of_the_request_over_the_hostname_of_the_channel(): void
    {
        $provider = new WebhookUrlProvider($this->urlGenerator, $this->request);

        self::assertSame('https://admin.example.com/payment-methods/PAYPAL', $provider->provide($this->paymentMethod('shop.example.com')));
    }

    public function test_it_takes_the_hostname_of_the_channel_on_the_console(): void
    {
        $provider = new WebhookUrlProvider($this->urlGenerator, $this->console);

        self::assertSame('https://shop.example.com/payment-methods/PAYPAL', $provider->provide($this->paymentMethod('shop.example.com')));
    }

    public function test_it_skips_a_localhost_a_blank_and_a_disabled_channel_on_the_console(): void
    {
        $provider = new WebhookUrlProvider($this->urlGenerator, $this->console);
        $paymentMethod = $this->paymentMethod('localhost', null, '', ['disabled.example.com', false], 'shop.example.com');

        self::assertSame('https://shop.example.com/payment-methods/PAYPAL', $provider->provide($paymentMethod));
    }

    public function test_it_falls_back_to_the_request_context_on_the_console_without_a_usable_channel_hostname(): void
    {
        $provider = new WebhookUrlProvider($this->urlGenerator, $this->console);

        self::assertSame('https://admin.example.com/payment-methods/PAYPAL', $provider->provide($this->paymentMethod('localhost')));
    }

    public function test_it_prefers_the_configured_base_url_over_the_request_and_the_channel(): void
    {
        foreach ([$this->request, $this->console] as $requestStack) {
            $provider = new WebhookUrlProvider($this->urlGenerator, $requestStack, 'https://sylius.ngrok.dev/');

            self::assertSame('https://sylius.ngrok.dev/payment-methods/PAYPAL', $provider->provide($this->paymentMethod('shop.example.com')));
        }
    }

    public function test_it_lists_the_notify_and_legacy_urls_on_every_host_the_webhook_may_be_registered_on(): void
    {
        $paymentMethod = $this->paymentMethod('shop.example.com', 'www.shop.example.com', 'localhost');

        self::assertSame(
            [
                'https://admin.example.com/payment-methods/PAYPAL',
                'https://admin.example.com/paypal-webhook/api/',
                'https://shop.example.com/payment-methods/PAYPAL',
                'https://shop.example.com/paypal-webhook/api/',
                'https://www.shop.example.com/payment-methods/PAYPAL',
                'https://www.shop.example.com/paypal-webhook/api/',
            ],
            (new WebhookUrlProvider($this->urlGenerator, $this->request))->provideCandidates($paymentMethod),
        );
        self::assertSame(
            [
                'https://sylius.ngrok.dev/payment-methods/PAYPAL',
                'https://sylius.ngrok.dev/paypal-webhook/api/',
                'https://shop.example.com/payment-methods/PAYPAL',
                'https://shop.example.com/paypal-webhook/api/',
                'https://www.shop.example.com/payment-methods/PAYPAL',
                'https://www.shop.example.com/paypal-webhook/api/',
                'https://admin.example.com/payment-methods/PAYPAL',
                'https://admin.example.com/paypal-webhook/api/',
            ],
            (new WebhookUrlProvider($this->urlGenerator, $this->console, 'https://sylius.ngrok.dev'))->provideCandidates($paymentMethod),
        );
    }

    public function test_it_keeps_a_non_default_port_and_the_base_path_of_the_request_context_in_the_legacy_url(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('http://shop.example.com:8080/shop/payment-methods/PAYPAL');
        $urlGenerator->method('getContext')->willReturn(new RequestContext(baseUrl: '/shop', host: 'shop.example.com', scheme: 'http', httpPort: 8080));

        self::assertContains(
            'http://shop.example.com:8080/shop/paypal-webhook/api/',
            (new WebhookUrlProvider($urlGenerator, $this->request))->provideCandidates($this->paymentMethod()),
        );
    }

    /** @param string|array{string, bool}|null ...$hostnames a hostname, or a hostname and whether its channel is enabled */
    private function paymentMethod(string|array|null ...$hostnames): PaymentMethodInterface
    {
        $channels = array_map(
            fn (string|array|null $hostname): ChannelInterface => $this->createConfiguredStub(ChannelInterface::class, [
                'getHostname' => is_array($hostname) ? $hostname[0] : $hostname,
                'isEnabled' => is_array($hostname) ? $hostname[1] : true,
            ]),
            $hostnames,
        );

        return $this->createConfiguredStub(PaymentMethodInterface::class, [
            'getCode' => 'PAYPAL',
            'getChannels' => new ArrayCollection($channels),
        ]);
    }
}
