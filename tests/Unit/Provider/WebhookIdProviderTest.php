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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\GenericApiInterface;
use Sylius\PayPalPlugin\Provider\HostProviderInterface;
use Sylius\PayPalPlugin\Provider\WebhookIdProvider;
use Sylius\PayPalPlugin\Provider\WebhookIdProviderInterface;
use Sylius\PayPalPlugin\Provider\WebhookUrlProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

final class WebhookIdProviderTest extends TestCase
{
    private const API_BASE_URL = 'https://api.sandbox.paypal.com/';

    private const WEBHOOKS_URL = 'https://api.sandbox.paypal.com/v1/notifications/webhooks';

    private const GENERATED_ABSOLUTE_URL = 'https://shop.example.com/paypal-webhook/api/';

    private GenericApiInterface&MockObject $genericApi;

    private UrlGeneratorInterface&MockObject $urlGenerator;

    private HostProviderInterface&MockObject $hostProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->genericApi = $this->createMock(GenericApiInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->hostProvider = $this->createMock(HostProviderInterface::class);
        $this->hostProvider->method('getApiBaseUrlForMode')->willReturnMap([
            [true, self::API_BASE_URL],
            [false, 'https://api.paypal.com/'],
        ]);
    }

    #[Test]
    public function it_implements_webhook_id_provider_interface(): void
    {
        self::assertInstanceOf(WebhookIdProviderInterface::class, $this->createProvider());
    }

    #[Test]
    public function it_returns_the_id_of_the_webhook_registered_for_the_generated_url(): void
    {
        $this->mockGeneratedUrls();
        $this->mockRegisteredWebhooks([
            ['id' => 'WH-OTHER', 'url' => 'https://shop.example.com/other/endpoint'],
            ['id' => 'WH-REFUND', 'url' => self::GENERATED_ABSOLUTE_URL],
        ]);

        self::assertSame('WH-REFUND', $this->createProvider()->provide($this->paymentMethod()));
    }

    public function test_it_returns_the_id_of_the_webhook_registered_for_the_notify_url_of_the_payment_method(): void
    {
        $this->mockGeneratedUrls();
        $this->mockRegisteredWebhooks([
            ['id' => 'WH-OTHER', 'url' => 'https://shop.example.com/payment-methods/OTHER'],
            ['id' => 'WH-NOTIFY', 'url' => 'https://shop.example.com/payment-methods/PAYPAL'],
        ]);

        self::assertSame('WH-NOTIFY', $this->createProvider()->provide($this->paymentMethod()));
    }

    public function test_it_prefers_the_webhook_of_the_notify_url_to_the_one_of_the_legacy_url(): void
    {
        $this->mockGeneratedUrls();
        $this->mockRegisteredWebhooks([
            ['id' => 'WH-LEGACY', 'url' => self::GENERATED_ABSOLUTE_URL],
            ['id' => 'WH-NOTIFY', 'url' => 'https://shop.example.com/payment-methods/PAYPAL'],
        ]);

        self::assertSame('WH-NOTIFY', $this->createProvider()->provide($this->paymentMethod()));
    }

    public function test_it_does_not_take_the_webhook_of_a_payment_method_whose_code_starts_the_same(): void
    {
        $this->mockGeneratedUrls();
        $this->mockRegisteredWebhooks([['id' => 'WH-PAYPAL', 'url' => 'https://shop.example.com/payment-methods/PAYPAL']]);

        self::assertNull($this->createProvider()->provide($this->paymentMethod(code: 'PAYPAL_2')));
    }

    #[Test]
    public function it_queries_the_paypal_webhooks_endpoint_for_the_payment_method(): void
    {
        $paymentMethod = $this->paymentMethod();
        $this->mockGeneratedUrls();

        $this->genericApi
            ->expects(self::once())
            ->method('get')
            ->with($paymentMethod, self::WEBHOOKS_URL)
            ->willReturn(['webhooks' => [['id' => 'WH-REFUND', 'url' => self::GENERATED_ABSOLUTE_URL]]]);

        self::assertSame('WH-REFUND', $this->createProvider()->provide($paymentMethod));
    }

    #[Test]
    public function it_queries_the_webhooks_endpoint_of_the_payment_method_mode(): void
    {
        $paymentMethod = $this->paymentMethod(false);
        $this->mockGeneratedUrls();

        $this->genericApi
            ->expects(self::once())
            ->method('get')
            ->with($paymentMethod, 'https://api.paypal.com/v1/notifications/webhooks')
            ->willReturn(['webhooks' => [['id' => 'WH-REFUND', 'url' => self::GENERATED_ABSOLUTE_URL]]]);

        self::assertSame('WH-REFUND', $this->createProvider()->provide($paymentMethod));
    }

    #[Test]
    public function it_returns_null_when_no_registered_webhook_matches(): void
    {
        $this->mockGeneratedUrls();
        $this->mockRegisteredWebhooks([
            ['id' => 'WH-A', 'url' => 'https://shop.example.com/different/endpoint'],
            ['id' => 'WH-B', 'url' => 'https://another-shop.example.com/paypal-webhook/api/'],
        ]);

        self::assertNull($this->createProvider()->provide($this->paymentMethod()));
    }

    #[Test]
    public function it_returns_null_when_there_are_no_registered_webhooks(): void
    {
        $this->mockGeneratedUrls();
        $this->genericApi->method('get')->willReturn([]);

        self::assertNull($this->createProvider()->provide($this->paymentMethod()));
    }

    #[Test]
    public function it_skips_webhooks_without_a_url_or_an_id(): void
    {
        $this->mockGeneratedUrls();
        $this->mockRegisteredWebhooks([
            ['url' => self::GENERATED_ABSOLUTE_URL],
            ['id' => 'WH-NO-URL'],
            ['id' => 'WH-REFUND', 'url' => self::GENERATED_ABSOLUTE_URL],
        ]);

        self::assertSame('WH-REFUND', $this->createProvider()->provide($this->paymentMethod()));
    }

    #[Test]
    #[DataProvider('matchingUrlProvider')]
    public function it_matches_regardless_of_scheme_trailing_slash_and_port(string $registeredUrl): void
    {
        $this->mockGeneratedUrls();
        $this->mockRegisteredWebhooks([['id' => 'WH-REFUND', 'url' => $registeredUrl]]);

        self::assertSame('WH-REFUND', $this->createProvider()->provide($this->paymentMethod()));
    }

    /** @return iterable<string, array{string}> */
    public static function matchingUrlProvider(): iterable
    {
        yield 'exact' => ['https://shop.example.com/paypal-webhook/api/'];
        yield 'no trailing slash' => ['https://shop.example.com/paypal-webhook/api'];
        yield 'http instead of https' => ['http://shop.example.com/paypal-webhook/api/'];
        yield 'explicit port' => ['https://shop.example.com:8443/paypal-webhook/api/'];
        yield 'uppercase host' => ['https://SHOP.EXAMPLE.COM/paypal-webhook/api/'];
    }

    #[Test]
    #[DataProvider('notMatchingUrlProvider')]
    public function it_does_not_match_when_domain_or_endpoint_differs(string $registeredUrl): void
    {
        $this->mockGeneratedUrls();
        $this->mockRegisteredWebhooks([['id' => 'WH-REFUND', 'url' => $registeredUrl]]);

        self::assertNull($this->createProvider()->provide($this->paymentMethod()));
    }

    /** @return iterable<string, array{string}> */
    public static function notMatchingUrlProvider(): iterable
    {
        yield 'different domain' => ['https://evil.example.com/paypal-webhook/api/'];
        yield 'different endpoint' => ['https://shop.example.com/some/other/path'];
        yield 'endpoint as prefix only' => ['https://shop.example.com/paypal-webhook/api/extra'];
    }

    #[Test]
    #[DataProvider('webhookBaseUrlFormatProvider')]
    public function it_builds_the_webhook_url_from_the_configured_base_url(
        string $webhookBaseUrl,
        string $registeredUrl,
    ): void {
        $this->mockGeneratedUrls();
        $this->mockRegisteredWebhooks([['id' => 'WH-REFUND', 'url' => $registeredUrl]]);

        self::assertSame('WH-REFUND', $this->createProvider($webhookBaseUrl)->provide($this->paymentMethod()));
    }

    /** @return iterable<string, array{string, string}> */
    public static function webhookBaseUrlFormatProvider(): iterable
    {
        yield 'without trailing slash' => [
            'https://shop.example.com',
            'https://shop.example.com/paypal-webhook/api/',
        ];
        yield 'with trailing slash' => [
            'https://shop.example.com/',
            'https://shop.example.com/paypal-webhook/api/',
        ];
        yield 'with path prefix' => [
            'https://shop.example.com/shop',
            'https://shop.example.com/shop/paypal-webhook/api/',
        ];
        yield 'with path prefix and trailing slash' => [
            'https://shop.example.com/shop/',
            'https://shop.example.com/shop/paypal-webhook/api/',
        ];
        yield 'base scheme differs from registered' => [
            'http://shop.example.com',
            'https://shop.example.com/paypal-webhook/api',
        ];
    }

    #[Test]
    public function it_refreshes_by_delegating_to_a_live_lookup(): void
    {
        $this->mockGeneratedUrls();
        $this->mockRegisteredWebhooks([['id' => 'WH-REFUND', 'url' => self::GENERATED_ABSOLUTE_URL]]);

        self::assertSame('WH-REFUND', $this->createProvider()->refresh($this->paymentMethod()));
    }

    #[Test]
    public function it_uses_the_configured_base_url_instead_of_the_absolute_url(): void
    {
        $this->mockGeneratedUrls();
        $this->mockRegisteredWebhooks([['id' => 'WH-REFUND', 'url' => 'https://configured.example.com/paypal-webhook/api/']]);

        self::assertSame(
            'WH-REFUND',
            $this->createProvider('https://configured.example.com')->provide($this->paymentMethod()),
        );
    }

    public function test_matches_when_the_generated_url_has_a_prefix_the_registered_one_lacks(): void
    {
        $this->mockGeneratedUrls();
        $this->mockRegisteredWebhooks([['id' => 'WH-REFUND', 'url' => 'https://shop.example.com/paypal-webhook/api/']]);

        self::assertSame(
            'WH-REFUND',
            $this->createProvider('https://shop.example.com/shop')->provide($this->paymentMethod()),
        );
    }

    public function test_does_not_match_a_registered_webhook_without_a_path(): void
    {
        $this->mockGeneratedUrls();
        $this->mockRegisteredWebhooks([['id' => 'WH-REFUND', 'url' => 'https://shop.example.com']]);

        self::assertNull($this->createProvider()->provide($this->paymentMethod()));
    }

    private function createProvider(string $webhookBaseUrl = ''): WebhookIdProvider
    {
        return new WebhookIdProvider(
            $this->genericApi,
            $this->hostProvider,
            new WebhookUrlProvider($this->urlGenerator, $webhookBaseUrl),
        );
    }

    private function paymentMethod(bool $sandbox = true, string $code = 'PAYPAL'): PaymentMethodInterface&MockObject
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getCode')->willReturn($code);
        $paymentMethod->method('getGatewayConfig')->willReturn(
            $this->createConfiguredStub(GatewayConfigInterface::class, ['getConfig' => ['sandbox' => $sandbox]]),
        );

        return $paymentMethod;
    }

    private function mockGeneratedUrls(): void
    {
        $this->urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $parameters, int $referenceType): string {
                $path = '/payment-methods/' . $parameters['code'];

                return UrlGeneratorInterface::ABSOLUTE_URL === $referenceType ? 'https://shop.example.com' . $path : $path;
            },
        );
        $this->urlGenerator->method('getContext')->willReturn(new RequestContext(host: 'shop.example.com', scheme: 'https'));
    }

    /** @param list<array<string, string>> $webhooks */
    private function mockRegisteredWebhooks(array $webhooks): void
    {
        $this->genericApi->method('get')->willReturn(['webhooks' => $webhooks]);
    }
}
