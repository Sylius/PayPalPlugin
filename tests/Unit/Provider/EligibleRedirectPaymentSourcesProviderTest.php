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
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\FindEligibleMethodsApiInterface;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;
use Sylius\PayPalPlugin\Provider\EligibleRedirectPaymentSourcesProvider;
use Sylius\PayPalPlugin\Provider\EligibleRedirectPaymentSourcesProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalFundingSourcesConfigurationProviderInterface;

final class EligibleRedirectPaymentSourcesProviderTest extends TestCase
{
    private CacheAuthorizeClientApiInterface&MockObject $authorizeClientApi;

    private FindEligibleMethodsApiInterface&MockObject $findEligibleMethodsApi;

    private PayPalFundingSourcesConfigurationProviderInterface&MockObject $fundingSourcesConfigurationProvider;

    private LoggerInterface&MockObject $logger;

    private PaymentInterface&MockObject $payment;

    private EligibleRedirectPaymentSourcesProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorizeClientApi = $this->createMock(CacheAuthorizeClientApiInterface::class);
        $this->findEligibleMethodsApi = $this->createMock(FindEligibleMethodsApiInterface::class);
        $this->fundingSourcesConfigurationProvider = $this->createMock(PayPalFundingSourcesConfigurationProviderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->payment = $this->payment('NL');

        $this->provider = new EligibleRedirectPaymentSourcesProvider(
            $this->authorizeClientApi,
            $this->findEligibleMethodsApi,
            $this->fundingSourcesConfigurationProvider,
            $this->logger,
        );
    }

    public function test_it_implements_eligible_redirect_payment_sources_provider_interface(): void
    {
        self::assertInstanceOf(EligibleRedirectPaymentSourcesProviderInterface::class, $this->provider);
    }

    public function test_it_asks_paypal_nothing_when_no_redirect_method_is_enabled(): void
    {
        $this->fundingSourcesConfigurationProvider->method('isTrustlyEnabled')->willReturn(false);

        $this->authorizeClientApi->expects(self::never())->method('authorize');
        $this->findEligibleMethodsApi->expects(self::never())->method('find');

        self::assertSame([], $this->provider->provide($this->payment));
    }

    public function test_it_asks_paypal_nothing_without_a_billing_country(): void
    {
        $this->fundingSourcesConfigurationProvider->method('isTrustlyEnabled')->willReturn(true);

        $this->findEligibleMethodsApi->expects(self::never())->method('find');

        self::assertSame([], $this->provider->provide($this->payment(null)));
    }

    public function test_it_asks_paypal_only_about_the_methods_the_merchant_opted_into(): void
    {
        $this->fundingSourcesConfigurationProvider->method('isTrustlyEnabled')->willReturn(true);
        $this->authorizeClientApi->method('authorize')->willReturn('TOKEN');

        $this->findEligibleMethodsApi
            ->expects(self::once())
            ->method('find')
            ->with('TOKEN', $this->payment, ['TRUSTLY'])
            ->willReturn(['eligible_methods' => ['trustly' => []]])
        ;

        self::assertSame([RedirectPaymentSource::Trustly], $this->provider->provide($this->payment));
    }

    public function test_it_offers_nothing_paypal_left_out_of_the_answer(): void
    {
        $this->fundingSourcesConfigurationProvider->method('isTrustlyEnabled')->willReturn(true);
        $this->findEligibleMethodsApi->method('find')->willReturn(['eligible_methods' => ['ideal' => []]]);

        self::assertSame([], $this->provider->provide($this->payment));
    }

    public function test_it_offers_nothing_when_paypal_cannot_be_reached(): void
    {
        $this->fundingSourcesConfigurationProvider->method('isTrustlyEnabled')->willReturn(true);
        $this->findEligibleMethodsApi->method('find')->willThrowException(new \RuntimeException('timeout'));

        $this->logger->expects(self::once())->method('error');

        self::assertSame([], $this->provider->provide($this->payment));
    }

    public function test_it_offers_nothing_when_paypal_answers_without_eligible_methods(): void
    {
        $this->fundingSourcesConfigurationProvider->method('isTrustlyEnabled')->willReturn(true);
        $this->findEligibleMethodsApi->method('find')->willReturn(['name' => 'UNPROCESSABLE_ENTITY']);

        $this->logger->expects(self::once())->method('error');

        self::assertSame([], $this->provider->provide($this->payment));
    }

    private function payment(?string $countryCode): PaymentInterface&MockObject
    {
        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress->method('getCountryCode')->willReturn($countryCode);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getBillingAddress')->willReturn($billingAddress);
        $order->method('getChannel')->willReturn($this->createMock(ChannelInterface::class));

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getMethod')->willReturn($this->createMock(PaymentMethodInterface::class));

        return $payment;
    }
}
