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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

final class WebhookUrlProviderTest extends TestCase
{
    private UrlGeneratorInterface&MockObject $urlGenerator;

    private PaymentMethodInterface $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $parameters, int $referenceType): string {
                $path = '/payment-methods/' . $parameters['code'];

                return UrlGeneratorInterface::ABSOLUTE_URL === $referenceType ? 'https://shop.example.com' . $path : $path;
            },
        );
        $this->urlGenerator->method('getContext')->willReturn(new RequestContext(host: 'shop.example.com', scheme: 'https'));
        $this->paymentMethod = $this->paymentMethod();
    }

    public function test_it_implements_webhook_url_provider_interface(): void
    {
        self::assertInstanceOf(
            WebhookUrlProviderInterface::class,
            new WebhookUrlProvider($this->urlGenerator),
        );
    }

    public function test_it_generates_the_absolute_notify_url_of_the_payment_method_from_the_request_context_by_default(): void
    {
        self::assertSame(
            'https://shop.example.com/payment-methods/PAYPAL',
            (new WebhookUrlProvider($this->urlGenerator))->provide($this->paymentMethod),
        );
    }

    public function test_it_prefers_the_configured_base_url_over_the_request_context(): void
    {
        self::assertSame(
            'https://sylius.ngrok.dev/payment-methods/PAYPAL',
            (new WebhookUrlProvider($this->urlGenerator, 'https://sylius.ngrok.dev'))->provide($this->paymentMethod),
        );
    }

    public function test_it_does_not_double_the_slash_of_a_base_url_that_has_one(): void
    {
        self::assertSame(
            'https://sylius.ngrok.dev/payment-methods/PAYPAL',
            (new WebhookUrlProvider($this->urlGenerator, 'https://sylius.ngrok.dev/'))->provide($this->paymentMethod),
        );
    }

    public function test_it_builds_the_legacy_webhook_url_from_the_request_context(): void
    {
        self::assertSame('https://shop.example.com/paypal-webhook/api/', (new WebhookUrlProvider($this->urlGenerator))->provideLegacy($this->paymentMethod));
    }

    public function test_it_builds_the_legacy_webhook_url_from_the_configured_base_url(): void
    {
        self::assertSame(
            'https://sylius.ngrok.dev/paypal-webhook/api/',
            (new WebhookUrlProvider($this->urlGenerator, 'https://sylius.ngrok.dev/'))->provideLegacy($this->paymentMethod),
        );
    }

    public function test_it_keeps_a_non_default_port_and_the_base_path_of_the_request_context_in_the_legacy_webhook_url(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('getContext')->willReturn(new RequestContext(baseUrl: '/shop', host: 'shop.example.com', scheme: 'http', httpPort: 8080));

        self::assertSame('http://shop.example.com:8080/shop/paypal-webhook/api/', (new WebhookUrlProvider($urlGenerator))->provideLegacy($this->paymentMethod));
    }

    public function test_it_takes_the_hostname_of_the_channel_of_the_payment_method_when_no_base_url_is_configured(): void
    {
        $paymentMethod = $this->paymentMethod(null, 'paypal-shop.example.com');
        $provider = new WebhookUrlProvider($this->urlGenerator);

        self::assertSame('https://paypal-shop.example.com/payment-methods/PAYPAL', $provider->provide($paymentMethod));
        self::assertSame('https://paypal-shop.example.com/paypal-webhook/api/', $provider->provideLegacy($paymentMethod));
    }

    public function test_it_prefers_the_configured_base_url_over_the_hostname_of_the_channel(): void
    {
        $paymentMethod = $this->paymentMethod('paypal-shop.example.com');
        $provider = new WebhookUrlProvider($this->urlGenerator, 'https://sylius.ngrok.dev');

        self::assertSame('https://sylius.ngrok.dev/payment-methods/PAYPAL', $provider->provide($paymentMethod));
        self::assertSame('https://sylius.ngrok.dev/paypal-webhook/api/', $provider->provideLegacy($paymentMethod));
    }

    private function paymentMethod(?string ...$hostnames): PaymentMethodInterface
    {
        $channels = array_map(
            fn (?string $hostname): ChannelInterface => $this->createConfiguredStub(ChannelInterface::class, ['getHostname' => $hostname]),
            $hostnames,
        );

        return $this->createConfiguredStub(PaymentMethodInterface::class, [
            'getCode' => 'PAYPAL',
            'getChannels' => new ArrayCollection($channels),
        ]);
    }
}
