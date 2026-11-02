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
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\FindEligibleMethodsApiInterface;
use Sylius\PayPalPlugin\Checker\PaymentAuthorizationCheckerInterface;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;
use Sylius\PayPalPlugin\Provider\EligibleRedirectPaymentSourcesProvider;
use Sylius\PayPalPlugin\Provider\EligibleRedirectPaymentSourcesProviderInterface;

final class EligibleRedirectPaymentSourcesProviderTest extends TestCase
{
    private FindEligibleMethodsApiInterface&MockObject $findEligibleMethodsApi;

    private LoggerInterface&MockObject $logger;

    private PaymentInterface&MockObject $payment;

    private EligibleRedirectPaymentSourcesProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->findEligibleMethodsApi = $this->createMock(FindEligibleMethodsApiInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $paymentAuthorizationChecker = $this->createMock(PaymentAuthorizationCheckerInterface::class);
        $paymentAuthorizationChecker->method('isAllowed')->willReturn(true);

        $this->payment = $this->payment('NL', true);

        $this->provider = new EligibleRedirectPaymentSourcesProvider(
            $this->findEligibleMethodsApi,
            $this->logger,
            $paymentAuthorizationChecker,
        );
    }

    public function test_it_implements_eligible_redirect_payment_sources_provider_interface(): void
    {
        self::assertInstanceOf(EligibleRedirectPaymentSourcesProviderInterface::class, $this->provider);
    }

    public function test_it_asks_paypal_nothing_when_no_redirect_method_is_enabled(): void
    {
        $this->findEligibleMethodsApi->expects(self::never())->method('find');

        self::assertSame([], $this->provider->provide($this->payment('NL', false)));
    }

    public function test_it_asks_paypal_nothing_about_a_method_the_payment_method_does_not_allow(): void
    {
        $paymentAuthorizationChecker = $this->createMock(PaymentAuthorizationCheckerInterface::class);
        $paymentAuthorizationChecker->method('isAllowed')->with('trustly', $this->payment->getMethod())->willReturn(false);

        $this->findEligibleMethodsApi->expects(self::never())->method('find');

        $provider = new EligibleRedirectPaymentSourcesProvider(
            $this->findEligibleMethodsApi,
            $this->logger,
            $paymentAuthorizationChecker,
        );

        self::assertSame([], $provider->provide($this->payment));
    }

    public function test_it_asks_paypal_nothing_without_a_billing_country(): void
    {
        $this->findEligibleMethodsApi->expects(self::never())->method('find');

        self::assertSame([], $this->provider->provide($this->payment(null, true)));
    }

    public function test_it_asks_paypal_only_about_the_methods_the_merchant_opted_into(): void
    {
        $this->findEligibleMethodsApi
            ->expects(self::once())
            ->method('find')
            ->with($this->payment->getMethod(), $this->payment, ['TRUSTLY'])
            ->willReturn(['eligible_methods' => ['trustly' => []]])
        ;

        self::assertSame([RedirectPaymentSource::Trustly], $this->provider->provide($this->payment));
    }

    public function test_it_offers_nothing_paypal_left_out_of_the_answer(): void
    {
        $this->findEligibleMethodsApi->method('find')->willReturn(['eligible_methods' => ['ideal' => []]]);

        self::assertSame([], $this->provider->provide($this->payment));
    }

    public function test_it_offers_nothing_when_paypal_cannot_be_reached(): void
    {
        $this->findEligibleMethodsApi->method('find')->willThrowException(new \RuntimeException('timeout'));

        $this->logger->expects(self::once())->method('error');

        self::assertSame([], $this->provider->provide($this->payment));
    }

    public function test_it_offers_nothing_when_paypal_answers_without_eligible_methods(): void
    {
        $this->findEligibleMethodsApi->method('find')->willReturn(['name' => 'UNPROCESSABLE_ENTITY']);

        $this->logger->expects(self::once())->method('error');

        self::assertSame([], $this->provider->provide($this->payment));
    }

    private function payment(?string $countryCode, bool $trustlyEnabled): PaymentInterface&MockObject
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setConfig([RedirectPaymentSource::Trustly->configurationKey() => $trustlyEnabled]);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress->method('getCountryCode')->willReturn($countryCode);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getBillingAddress')->willReturn($billingAddress);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getMethod')->willReturn($paymentMethod);

        return $payment;
    }
}
