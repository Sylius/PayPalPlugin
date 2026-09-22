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
use Sylius\PayPalPlugin\Provider\PayPalWebhookUrlProvider;
use Sylius\PayPalPlugin\Provider\PayPalWebhookUrlProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PayPalWebhookUrlProviderTest extends TestCase
{
    private UrlGeneratorInterface&MockObject $urlGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters, int $type): string => UrlGeneratorInterface::ABSOLUTE_URL === $type
                    ? 'https://shop.example.com/paypal-webhook/api/'
                    : '/paypal-webhook/api/',
        );
    }

    public function test_it_implements_paypal_webhook_url_provider_interface(): void
    {
        self::assertInstanceOf(
            PayPalWebhookUrlProviderInterface::class,
            new PayPalWebhookUrlProvider($this->urlGenerator),
        );
    }

    public function test_it_generates_an_absolute_url_from_the_request_context_by_default(): void
    {
        self::assertSame(
            'https://shop.example.com/paypal-webhook/api/',
            (new PayPalWebhookUrlProvider($this->urlGenerator))->provide(),
        );
    }

    public function test_it_prefers_the_configured_base_url_over_the_request_context(): void
    {
        self::assertSame(
            'https://sylius.ngrok.dev/paypal-webhook/api/',
            (new PayPalWebhookUrlProvider($this->urlGenerator, 'https://sylius.ngrok.dev'))->provide(),
        );
    }

    public function test_it_does_not_double_the_slash_of_a_base_url_that_has_one(): void
    {
        self::assertSame(
            'https://sylius.ngrok.dev/paypal-webhook/api/',
            (new PayPalWebhookUrlProvider($this->urlGenerator, 'https://sylius.ngrok.dev/'))->provide(),
        );
    }
}
