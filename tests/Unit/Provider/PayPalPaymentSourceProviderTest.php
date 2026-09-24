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
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Exception\InvalidPayerDataException;
use Sylius\PayPalPlugin\Exception\UnsupportedPayPalPaymentSourceException;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProvider;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;

final class PayPalPaymentSourceProviderTest extends TestCase
{
    private OrderInterface&MockObject $order;

    private PayPalPaymentSourceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->order = $this->createMock(OrderInterface::class);
        $this->provider = new PayPalPaymentSourceProvider();
    }

    public function test_it_implements_paypal_payment_source_provider_interface(): void
    {
        self::assertInstanceOf(PayPalPaymentSourceProviderInterface::class, $this->provider);
    }

    public function test_it_wraps_the_experience_context_in_the_paypal_payment_source(): void
    {
        $experienceContext = ['locale' => 'en-US', 'user_action' => 'PAY_NOW'];

        self::assertSame(
            ['paypal' => ['experience_context' => $experienceContext]],
            $this->provider->provide($this->order, PayPalPaymentSourceProviderInterface::PAYPAL, $experienceContext),
        );
    }

    public function test_it_asks_for_regulatory_authentication_on_google_pay(): void
    {
        self::assertSame(
            ['google_pay' => ['attributes' => ['verification' => ['method' => 'SCA_WHEN_REQUIRED']]]],
            $this->provider->provide($this->order, PayPalPaymentSourceProviderInterface::GOOGLE_PAY, []),
        );
    }

    public function test_it_sends_no_experience_context_with_google_pay(): void
    {
        $googlePay = $this->provider->provide(
            $this->order,
            PayPalPaymentSourceProviderInterface::GOOGLE_PAY,
            ['locale' => 'en-US', 'return_url' => 'https://shop.example.com/checkout/complete'],
        );

        self::assertArrayNotHasKey('experience_context', $googlePay['google_pay']);
    }

    public function test_it_identifies_the_payer_to_trustly_from_the_billing_address_and_the_customer(): void
    {
        $this->orderIsBilledTo('Patrick Watson', 'NL', 'patrick.watson@example.com');

        self::assertSame(
            [
                'name' => 'Patrick Watson',
                'country_code' => 'NL',
                'email' => 'patrick.watson@example.com',
                'experience_context' => [],
            ],
            $this->provider->provide($this->order, PayPalPaymentSourceProviderInterface::TRUSTLY, [])['trustly'],
        );
    }

    public function test_it_sends_only_the_base_experience_context_keys_with_trustly(): void
    {
        $this->orderIsBilledTo('Patrick Watson', 'NL', 'patrick.watson@example.com');

        $trustly = $this->provider->provide($this->order, PayPalPaymentSourceProviderInterface::TRUSTLY, [
            'locale' => 'nl-NL',
            'shipping_preference' => 'SET_PROVIDED_ADDRESS',
            'return_url' => 'https://shop.example.com/return',
            'cancel_url' => 'https://shop.example.com/cancel',
            'user_action' => 'PAY_NOW',
            'contact_preference' => 'RETAIN_CONTACT_INFO',
            'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
            'app_switch_preference' => ['launch_paypal_app' => true],
            'order_update_callback_config' => ['callback_url' => 'https://shop.example.com/callback'],
        ]);

        self::assertSame(
            [
                'locale' => 'nl-NL',
                'shipping_preference' => 'SET_PROVIDED_ADDRESS',
                'return_url' => 'https://shop.example.com/return',
                'cancel_url' => 'https://shop.example.com/cancel',
            ],
            $trustly['trustly']['experience_context'],
        );
    }

    public function test_it_supports_the_trustly_payment_source(): void
    {
        self::assertTrue($this->provider->supports(PayPalPaymentSourceProviderInterface::TRUSTLY));
    }

    public function test_it_refuses_to_pay_with_trustly_without_a_billing_address(): void
    {
        $this->order->method('getBillingAddress')->willReturn(null);

        $this->expectException(InvalidPayerDataException::class);
        $this->expectExceptionMessage('The PayPal order needs a billing address to be paid with "trustly"');

        $this->provider->provide($this->order, PayPalPaymentSourceProviderInterface::TRUSTLY, []);
    }

    public function test_it_refuses_to_pay_with_trustly_without_a_customer_email(): void
    {
        $this->orderIsBilledTo('Patrick Watson', 'NL', null);

        $this->expectException(InvalidPayerDataException::class);
        $this->expectExceptionMessage('The PayPal order needs the payer email to be paid with "trustly"');

        $this->provider->provide($this->order, PayPalPaymentSourceProviderInterface::TRUSTLY, []);
    }

    public function test_it_refuses_to_pay_with_trustly_without_a_payer_name(): void
    {
        $this->orderIsBilledTo('   ', 'NL', 'patrick.watson@example.com');

        $this->expectException(InvalidPayerDataException::class);
        $this->expectExceptionMessage('The PayPal order needs the payer name to be paid with "trustly"');

        $this->provider->provide($this->order, PayPalPaymentSourceProviderInterface::TRUSTLY, []);
    }

    public function test_it_refuses_to_pay_with_trustly_from_a_country_code_paypal_does_not_take(): void
    {
        $this->orderIsBilledTo('Patrick Watson', 'nl', 'patrick.watson@example.com');

        $this->expectException(InvalidPayerDataException::class);
        $this->expectExceptionMessage('PayPal does not accept the country code "nl"');

        $this->provider->provide($this->order, PayPalPaymentSourceProviderInterface::TRUSTLY, []);
    }

    private function orderIsBilledTo(string $fullName, string $countryCode, ?string $email): void
    {
        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress->method('getFullName')->willReturn($fullName);
        $billingAddress->method('getCountryCode')->willReturn($countryCode);

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn($email);

        $this->order->method('getBillingAddress')->willReturn($billingAddress);
        $this->order->method('getCustomer')->willReturn($customer);
    }

    public function test_it_asks_for_regulatory_authentication_on_card(): void
    {
        $card = $this->provider->provide($this->order, PayPalPaymentSourceProviderInterface::CARD, []);

        self::assertSame(
            ['method' => 'SCA_WHEN_REQUIRED'],
            $card['card']['attributes']['verification'],
        );
    }

    public function test_it_sends_its_own_experience_context_with_card(): void
    {
        $card = $this->provider->provide(
            $this->order,
            PayPalPaymentSourceProviderInterface::CARD,
            [
                'locale' => 'en-US',
                'user_action' => 'PAY_NOW',
                'return_url' => 'https://shop.example.com/checkout/complete',
                'cancel_url' => 'https://shop.example.com/checkout/complete',
            ],
        );

        self::assertSame(
            [
                'return_url' => 'https://shop.example.com/checkout/complete',
                'cancel_url' => 'https://shop.example.com/checkout/complete',
            ],
            $card['card']['experience_context'],
        );
    }

    public function test_it_sends_an_empty_experience_context_with_card_when_no_urls_are_given(): void
    {
        $card = $this->provider->provide($this->order, PayPalPaymentSourceProviderInterface::CARD, ['locale' => 'en-US']);

        self::assertSame([], $card['card']['experience_context']);
    }

    public function test_it_sends_only_the_fields_venmo_wallet_experience_context_accepts(): void
    {
        $experienceContext = [
            'locale' => 'en-US',
            'shipping_preference' => 'GET_FROM_FILE',
            'contact_preference' => 'RETAIN_CONTACT_INFO',
            'user_action' => 'PAY_NOW',
            'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
            'return_url' => 'https://shop.example.com/checkout/complete',
            'cancel_url' => 'https://shop.example.com/checkout/complete',
        ];

        $venmo = $this->provider->provide($this->order, PayPalPaymentSourceProviderInterface::VENMO, $experienceContext);

        self::assertSame(
            ['shipping_preference' => 'GET_FROM_FILE', 'user_action' => 'PAY_NOW'],
            $venmo['venmo']['experience_context'],
        );
    }

    public function test_it_sends_an_empty_experience_context_with_venmo_when_nothing_applicable_is_given(): void
    {
        $venmo = $this->provider->provide($this->order, PayPalPaymentSourceProviderInterface::VENMO, ['locale' => 'en-US']);

        self::assertSame([], $venmo['venmo']['experience_context']);
    }

    public function test_it_forwards_the_order_update_callback_config_with_venmo(): void
    {
        $callbackConfig = [
            'callback_events' => ['SHIPPING_ADDRESS'],
            'callback_url' => 'https://shop.example.com/paypal/order-shipping-callback',
        ];

        $venmo = $this->provider->provide($this->order, PayPalPaymentSourceProviderInterface::VENMO, [
            'order_update_callback_config' => $callbackConfig,
        ]);

        self::assertSame($callbackConfig, $venmo['venmo']['experience_context']['order_update_callback_config']);
    }

    public function test_it_supports_the_paypal_payment_source(): void
    {
        self::assertTrue($this->provider->supports(PayPalPaymentSourceProviderInterface::PAYPAL));
    }

    public function test_it_supports_the_google_pay_payment_source(): void
    {
        self::assertTrue($this->provider->supports(PayPalPaymentSourceProviderInterface::GOOGLE_PAY));
    }

    public function test_it_supports_the_card_payment_source(): void
    {
        self::assertTrue($this->provider->supports(PayPalPaymentSourceProviderInterface::CARD));
    }

    public function test_it_supports_the_venmo_payment_source(): void
    {
        self::assertTrue($this->provider->supports(PayPalPaymentSourceProviderInterface::VENMO));
    }

    public function test_it_does_not_support_an_unknown_payment_source(): void
    {
        self::assertFalse($this->provider->supports('bitcoin'));
    }

    public function test_it_throws_an_exception_when_the_payment_source_is_not_supported(): void
    {
        $this->expectException(UnsupportedPayPalPaymentSourceException::class);
        $this->expectExceptionMessage('PayPal payment source "bitcoin" is not supported');

        $this->provider->provide($this->order, 'bitcoin', []);
    }
}
