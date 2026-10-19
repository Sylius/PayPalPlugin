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
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Provider\WebhookUrlProvider;
use Sylius\PayPalPlugin\Provider\WebhookUrlProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
                $path = match ($route) {
                    'sylius_payment_method_notify' => '/payment-methods/' . $parameters['code'],
                    'sylius_paypal_webhook_refund_order' => '/paypal-webhook/api/',
                };

                return UrlGeneratorInterface::ABSOLUTE_URL === $referenceType ? 'https://shop.example.com' . $path : $path;
            },
        );
        $this->paymentMethod = $this->createConfiguredStub(PaymentMethodInterface::class, ['getCode' => 'PAYPAL']);
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

    public function test_it_generates_the_legacy_webhook_url(): void
    {
        self::assertSame('https://shop.example.com/paypal-webhook/api/', (new WebhookUrlProvider($this->urlGenerator))->provideLegacy());
        self::assertSame(
            'https://sylius.ngrok.dev/paypal-webhook/api/',
            (new WebhookUrlProvider($this->urlGenerator, 'https://sylius.ngrok.dev'))->provideLegacy(),
        );
    }
}
