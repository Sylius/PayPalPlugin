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

namespace Tests\Sylius\PayPalPlugin\Unit\OrderPay\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ApiBundle\SectionResolver\ShopApiSection;
use Sylius\Bundle\CoreBundle\OrderPay\Provider\UrlProviderInterface;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionProviderInterface;
use Sylius\Bundle\ShopBundle\SectionResolver\ShopSection;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\OrderPay\Provider\ShopApiReturnUrlProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class ShopApiReturnUrlProviderTest extends TestCase
{
    private const HASH = '01a18276-7b65-799e-b475-dd049f5a8633';

    private const SHOP_URL = 'https://shop.example.com/en_US/payment-request/pay/' . self::HASH;

    private UrlProviderInterface&MockObject $decoratedUrlProvider;

    private SectionProviderInterface&MockObject $sectionProvider;

    private PaymentRequestInterface&MockObject $paymentRequest;

    protected function setUp(): void
    {
        $this->decoratedUrlProvider = $this->createMock(UrlProviderInterface::class);
        $this->sectionProvider = $this->createMock(SectionProviderInterface::class);
        $this->paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $this->paymentRequest->method('getHash')->willReturn(Uuid::fromString(self::HASH));
        $this->decoratedUrlProvider
            ->method('getUrl')
            ->with($this->paymentRequest, UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn(self::SHOP_URL)
        ;
    }

    #[Test]
    public function it_returns_the_configured_url_of_the_payment_request_in_the_shop_api(): void
    {
        $this->sectionProvider->method('getSection')->willReturn(new ShopApiSection());

        self::assertSame(
            'https://headless.example.com/paypal/return/' . self::HASH,
            $this->provider('https://headless.example.com/paypal/return/{hash}')->getUrl($this->paymentRequest, UrlGeneratorInterface::ABSOLUTE_URL),
        );
    }

    #[Test]
    public function it_returns_the_shop_url_outside_the_shop_api(): void
    {
        $this->sectionProvider->method('getSection')->willReturn(new ShopSection());

        self::assertSame(
            self::SHOP_URL,
            $this->provider('https://headless.example.com/paypal/return/{hash}')->getUrl($this->paymentRequest, UrlGeneratorInterface::ABSOLUTE_URL),
        );
    }

    #[Test]
    public function it_returns_the_shop_url_without_a_section(): void
    {
        $this->sectionProvider->method('getSection')->willReturn(null);

        self::assertSame(
            self::SHOP_URL,
            $this->provider('https://headless.example.com/paypal/return/{hash}')->getUrl($this->paymentRequest, UrlGeneratorInterface::ABSOLUTE_URL),
        );
    }

    #[Test]
    public function it_returns_the_shop_url_in_the_shop_api_when_no_url_is_configured(): void
    {
        $this->sectionProvider->method('getSection')->willReturn(new ShopApiSection());

        self::assertSame(self::SHOP_URL, $this->provider(null)->getUrl($this->paymentRequest, UrlGeneratorInterface::ABSOLUTE_URL));
    }

    #[Test]
    public function it_requires_the_hash_placeholder_in_the_configured_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->provider('https://headless.example.com/paypal/return');
    }

    private function provider(?string $shopApiReturnUrl): ShopApiReturnUrlProvider
    {
        return new ShopApiReturnUrlProvider($this->decoratedUrlProvider, $this->sectionProvider, $shopApiReturnUrl);
    }
}
