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
use Sylius\PayPalPlugin\Provider\PayPalOnboardingUrlProvider;
use Sylius\PayPalPlugin\Provider\PayPalOnboardingUrlProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PayPalOnboardingUrlProviderTest extends TestCase
{
    private UrlGeneratorInterface&MockObject $urlGenerator;

    private PayPalOnboardingUrlProvider $payPalOnboardingUrlProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->payPalOnboardingUrlProvider = new PayPalOnboardingUrlProvider(
            'https://www.sandbox.paypal.com',
            'PARTNER-ID',
            'PARTNER-CLIENT-ID',
            'https://shop.example.com/logo.png',
            $this->urlGenerator,
        );
    }

    #[Test]
    public function it_implements_paypal_onboarding_url_provider_interface(): void
    {
        self::assertInstanceOf(PayPalOnboardingUrlProviderInterface::class, $this->payPalOnboardingUrlProvider);
    }

    #[Test]
    public function it_generates_the_bizsignup_partner_entry_url_with_a_slash_between_web_url_and_path(): void
    {
        $this->urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('sylius_admin_payment_method_index', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://shop.example.com/admin/payment-methods/');

        $result = $this->payPalOnboardingUrlProvider->generate('SELLER-NONCE');

        self::assertStringStartsWith('https://www.sandbox.paypal.com/bizsignup/partner/entry?', $result);
        self::assertStringContainsString('partnerId=PARTNER-ID', $result);
        self::assertStringContainsString('partnerClientId=PARTNER-CLIENT-ID', $result);
        self::assertStringContainsString('product=express_checkout', $result);
        self::assertStringContainsString('integrationType=FO', $result);
        self::assertStringContainsString('displayMode=minibrowser', $result);
        self::assertStringContainsString('sellerNonce=SELLER-NONCE', $result);
        self::assertStringContainsString('partnerLogoUrl=' . rawurlencode('https://shop.example.com/logo.png'), $result);
        self::assertStringContainsString(rawurlencode('https://shop.example.com/admin/payment-methods/'), $result);
    }
}
