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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\GenericApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalWrongDataException;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalRefundDataProvider;

final class PayPalRefundDataProviderTest extends TestCase
{
    private CacheAuthorizeClientApiInterface&MockObject $authorizeClientApi;

    private GenericApiInterface&MockObject $genericApi;

    private PayPalPaymentMethodProviderInterface&MockObject $payPalPaymentMethodProvider;

    private PayPalRefundDataProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorizeClientApi = $this->createMock(CacheAuthorizeClientApiInterface::class);
        $this->genericApi = $this->createMock(GenericApiInterface::class);
        $this->payPalPaymentMethodProvider = $this->createMock(PayPalPaymentMethodProviderInterface::class);

        $this->provider = new PayPalRefundDataProvider(
            $this->authorizeClientApi,
            $this->genericApi,
            $this->payPalPaymentMethodProvider,
        );
    }

    #[Test]
    public function provides_data_from_provided_url(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $this->payPalPaymentMethodProvider->method('provide')->willReturn($paymentMethod);
        $this->authorizeClientApi->method('authorize')->with($paymentMethod)->willReturn('TOKEN');

        $this->genericApi->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function ($token, $url) {
                $this->assertEquals('TOKEN', $token);

                if ($url === 'https://get-refund-data.com') {
                    return [
                        'links' => [
                            ['rel' => 'self', 'href' => 'https://self.url.com'],
                            ['rel' => 'up', 'href' => 'https://up.url.com'],
                        ],
                    ];
                }
                if ($url === 'https://up.url.com') {
                    return ['data' => 'refund-data'];
                }

                $this->fail("Unexpected call to get() with URL: $url");
            });

        $this->provider->provide('https://get-refund-data.com');
    }

    #[Test]
    public function throws_error_if_paypal_data_doesnt_contain_url(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $this->payPalPaymentMethodProvider->method('provide')->willReturn($paymentMethod);
        $this->authorizeClientApi->method('authorize')->with($paymentMethod)->willReturn('TOKEN');
        $this->genericApi->method('get')
            ->with('TOKEN', 'https://get-refund-data.com')
            ->willReturn([
                'links' => [
                    ['rel' => 'self', 'href' => 'https://self.url.com'],
                    ['rel' => 'get', 'href' => 'https://get.url.com'],
                ],
            ]);

        $this->expectException(PayPalWrongDataException::class);
        $this->provider->provide('https://get-refund-data.com');
    }

    public function test_it_reports_a_paypal_response_that_carries_no_links_at_all(): void
    {
        $this->authorizeTokenFor('https://get-refund-data.com', ['id' => 'REFUND_ID']);

        $this->expectException(PayPalWrongDataException::class);

        $this->provider->provide('https://get-refund-data.com');
    }

    public function test_it_reports_a_refund_whose_up_link_carries_no_address(): void
    {
        $this->authorizeTokenFor('https://get-refund-data.com', [
            'links' => [['rel' => 'up']],
        ]);

        $this->expectException(PayPalWrongDataException::class);

        $this->provider->provide('https://get-refund-data.com');
    }

    /** @param array<string, mixed> $response */
    private function authorizeTokenFor(string $url, array $response): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $this->payPalPaymentMethodProvider->method('provide')->willReturn($paymentMethod);
        $this->authorizeClientApi->method('authorize')->with($paymentMethod)->willReturn('TOKEN');
        $this->genericApi->method('get')->with('TOKEN', $url)->willReturn($response);
    }
}
