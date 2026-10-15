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

namespace Tests\Sylius\PayPalPlugin\Unit\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\GenericApi;
use Sylius\PayPalPlugin\Api\GenericApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Exception\UntrustedPayPalUrlException;
use Sylius\PayPalPlugin\Provider\HostProviderInterface;

final class GenericApiTest extends TestCase
{
    private PayPalClientInterface&MockObject $client;

    private GenericApi $genericApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PayPalClientInterface::class);

        $hostProvider = $this->createStub(HostProviderInterface::class);
        $hostProvider->method('getApiBaseUrlForMode')->willReturnMap([
            [true, HostProviderInterface::SANDBOX_API_BASE_URL],
            [false, HostProviderInterface::PRODUCTION_API_BASE_URL],
        ]);

        $this->genericApi = new GenericApi($this->client, $hostProvider);
    }

    public function test_it_implements_the_generic_api_interface(): void
    {
        self::assertInstanceOf(GenericApiInterface::class, $this->genericApi);
    }

    public function test_it_reads_a_paypal_api_link_through_the_paypal_client(): void
    {
        $paymentMethod = $this->paymentMethodInMode(sandbox: true);

        $this->client->expects(self::once())->method('get')->with($paymentMethod, 'v2/payments/captures/CAPTURE_ID?fields=links')->willReturn(['id' => 'CAPTURE_ID']);

        self::assertSame(['id' => 'CAPTURE_ID'], $this->genericApi->get($paymentMethod, 'https://api.sandbox.paypal.com/v2/payments/captures/CAPTURE_ID?fields=links'));
    }

    public function test_it_reads_a_link_to_the_api_m_host_of_the_same_environment(): void
    {
        $paymentMethod = $this->paymentMethodInMode(sandbox: false);

        $this->client->expects(self::once())->method('get')->with($paymentMethod, 'v2/checkout/orders/ORDER_ID')->willReturn(['id' => 'ORDER_ID']);

        self::assertSame(['id' => 'ORDER_ID'], $this->genericApi->get($paymentMethod, 'https://API-M.PayPal.com/v2/checkout/orders/ORDER_ID'));
    }

    #[DataProvider('untrustedUrls')]
    public function test_it_refuses_to_send_the_paypal_credentials_to_an_untrusted_url(bool $sandbox, string $url): void
    {
        $this->client->expects(self::never())->method('get');

        $this->expectException(UntrustedPayPalUrlException::class);

        $this->genericApi->get($this->paymentMethodInMode($sandbox), $url);
    }

    /** @return iterable<string, array{bool, string}> */
    public static function untrustedUrls(): iterable
    {
        yield 'a foreign host' => [true, 'https://attacker.example.com/v2/payments/captures/CAPTURE_ID'];
        yield 'a host that only starts like PayPal' => [true, 'https://api.sandbox.paypal.com.attacker.example.com/v2/payments/captures/CAPTURE_ID'];
        yield 'a host that only ends like PayPal' => [false, 'https://attacker-api.paypal.com/v2/payments/captures/CAPTURE_ID'];
        yield 'plain http' => [true, 'http://api.sandbox.paypal.com/v2/payments/captures/CAPTURE_ID'];
        yield 'the live API for a sandbox payment method' => [true, 'https://api-m.paypal.com/v2/payments/captures/CAPTURE_ID'];
        yield 'the sandbox API for a live payment method' => [false, 'https://api.sandbox.paypal.com/v2/payments/captures/CAPTURE_ID'];
        yield 'credentials in the URL' => [true, 'https://user@api.sandbox.paypal.com/v2/payments/captures/CAPTURE_ID'];
        yield 'a port' => [true, 'https://api.sandbox.paypal.com:8443/v2/payments/captures/CAPTURE_ID'];
        yield 'a relative URL' => [true, 'v2/payments/captures/CAPTURE_ID'];
        yield 'a malformed URL' => [true, 'https:///v2/payments/captures/CAPTURE_ID'];
    }

    public function test_it_refuses_to_read_an_error_paypal_answered_with(): void
    {
        $this->client->method('get')->willReturn(['name' => 'RESOURCE_NOT_FOUND', 'debug_id' => 'DEBUG_ID']);

        $this->expectException(PayPalPluginException::class);

        $this->genericApi->get($this->paymentMethodInMode(sandbox: true), 'https://api.sandbox.paypal.com/v2/payments/captures/CAPTURE_ID');
    }

    public function test_it_refuses_to_read_a_response_without_a_paypal_resource(): void
    {
        $this->client->method('get')->willReturn([]);

        $this->expectException(PayPalPluginException::class);

        $this->genericApi->get($this->paymentMethodInMode(sandbox: true), 'https://api.sandbox.paypal.com/v2/payments/captures/CAPTURE_ID');
    }

    private function paymentMethodInMode(bool $sandbox): PaymentMethodInterface&MockObject
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($this->createConfiguredStub(GatewayConfigInterface::class, ['getConfig' => ['sandbox' => $sandbox]]));

        return $paymentMethod;
    }
}
