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

namespace Tests\Sylius\PayPalPlugin\Unit\Factory;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Factory\PayPalOrderFactory;
use Sylius\PayPalPlugin\Factory\PayPalOrderFactoryInterface;
use Sylius\PayPalPlugin\Factory\PayPalPurchaseUnitFactoryInterface;
use Sylius\PayPalPlugin\Model\PayPalPurchaseUnit;
use Sylius\PayPalPlugin\Provider\ExperienceContextProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalShippingCallbackUrlProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PayPalOrderFactoryTest extends TestCase
{
    private PayPalPurchaseUnitFactoryInterface&MockObject $payPalPurchaseUnitFactory;

    private UrlGeneratorInterface&MockObject $router;

    private PayPalShippingCallbackUrlProviderInterface&MockObject $shippingCallbackUrlProvider;

    private OrderInterface&MockObject $order;

    private PaymentInterface&MockObject $payment;

    private PayPalOrderFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payPalPurchaseUnitFactory = $this->createMock(PayPalPurchaseUnitFactoryInterface::class);
        $this->router = $this->createMock(UrlGeneratorInterface::class);
        $this->shippingCallbackUrlProvider = $this->createMock(PayPalShippingCallbackUrlProviderInterface::class);
        $this->order = $this->createMock(OrderInterface::class);
        $this->payment = $this->createMock(PaymentInterface::class);

        $this->payment->method('getOrder')->willReturn($this->order);
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);

        $this->router->method('generate')->willReturn('https://shop.example.com/checkout/complete');
        $this->shippingCallbackUrlProvider
            ->method('provide')
            ->willReturn('https://shop.example.com/paypal/order-shipping-callback')
        ;
        $this->payPalPurchaseUnitFactory->method('create')->willReturn($this->purchaseUnit());

        $this->factory = new PayPalOrderFactory(
            $this->payPalPurchaseUnitFactory,
            $this->router,
            $this->shippingCallbackUrlProvider,
        );
    }

    public function test_it_implements_paypal_order_factory_interface(): void
    {
        self::assertInstanceOf(PayPalOrderFactoryInterface::class, $this->factory);
    }

    public function test_it_captures_and_delegates_the_purchase_unit_to_its_own_factory(): void
    {
        $payPalPurchaseUnitFactory = $this->createMock(PayPalPurchaseUnitFactoryInterface::class);
        $payPalPurchaseUnitFactory
            ->expects(self::once())
            ->method('create')
            ->with($this->payment, 'REFERENCE_ID')
            ->willReturn($this->purchaseUnit())
        ;

        $payPalOrder = (new PayPalOrderFactory($payPalPurchaseUnitFactory, $this->router))
            ->create($this->payment, 'REFERENCE_ID')
            ->toArray()
        ;

        self::assertSame('CAPTURE', $payPalOrder['intent']);
        self::assertSame('REFERENCE_ID', $payPalOrder['purchase_units'][0]['reference_id']);
    }

    public function test_it_sends_the_same_return_and_cancel_url_on_orders_addressed_in_the_wallet(): void
    {
        $payPalOrder = $this->factory->create($this->payment, 'REFERENCE_ID')->toArray();

        self::assertSame([
            'shipping_preference' => 'GET_FROM_FILE',
            'contact_preference' => 'UPDATE_CONTACT_INFO',
            'user_action' => 'PAY_NOW',
            'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
            'return_url' => 'https://shop.example.com/checkout/complete',
            'cancel_url' => 'https://shop.example.com/checkout/complete',
            'app_switch_preference' => [
                'launch_paypal_app' => true,
            ],
            'order_update_callback_config' => [
                'callback_events' => ['SHIPPING_ADDRESS'],
                'callback_url' => 'https://shop.example.com/paypal/order-shipping-callback',
            ],
        ], $payPalOrder['payment_source']['paypal']['experience_context']);
    }

    public function test_it_omits_the_brand_name_and_normalises_the_locale(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('isShippingRequired')->willReturn(true);
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getLocaleCode')->willReturn('en_US');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $experienceContext = $this->factory
            ->create($payment, 'REFERENCE_ID')
            ->toArray()['payment_source']['paypal']['experience_context']
        ;

        self::assertArrayNotHasKey('brand_name', $experienceContext);
        self::assertSame('en-US', $experienceContext['locale']);
    }

    public function test_it_sends_the_experience_context_when_the_address_is_already_provided(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('isShippingRequired')->willReturn(true);
        $order->method('getShippingAddress')->willReturn($this->createMock(AddressInterface::class));

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $payPalOrder = $this->factory->create($payment, 'REFERENCE_ID')->toArray();

        self::assertArrayNotHasKey('application_context', $payPalOrder);
        self::assertSame([
            'shipping_preference' => 'SET_PROVIDED_ADDRESS',
            'contact_preference' => 'RETAIN_CONTACT_INFO',
            'user_action' => 'PAY_NOW',
            'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
            'return_url' => 'https://shop.example.com/checkout/complete',
            'cancel_url' => 'https://shop.example.com/checkout/complete',
            'app_switch_preference' => [
                'launch_paypal_app' => true,
            ],
        ], $payPalOrder['payment_source']['paypal']['experience_context']);
    }

    public function test_it_declares_no_shipping_callback_when_its_provider_has_no_url_to_give(): void
    {
        $shippingCallbackUrlProvider = $this->createMock(PayPalShippingCallbackUrlProviderInterface::class);
        $shippingCallbackUrlProvider->method('provide')->willReturn(null);

        $experienceContext = (new PayPalOrderFactory(
            $this->payPalPurchaseUnitFactory,
            $this->router,
            $shippingCallbackUrlProvider,
        ))
            ->create($this->payment, 'REFERENCE_ID')
            ->toArray()['payment_source']['paypal']['experience_context']
        ;

        self::assertArrayNotHasKey('order_update_callback_config', $experienceContext);
        self::assertSame('https://shop.example.com/checkout/complete', $experienceContext['return_url']);
    }

    public function test_it_sends_no_urls_at_all_without_a_router(): void
    {
        $payPalOrder = (new PayPalOrderFactory($this->payPalPurchaseUnitFactory))
            ->create($this->payment, 'REFERENCE_ID')
            ->toArray()
        ;

        self::assertSame([
            'shipping_preference' => 'GET_FROM_FILE',
            'contact_preference' => 'UPDATE_CONTACT_INFO',
            'user_action' => 'PAY_NOW',
            'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
            'app_switch_preference' => [
                'launch_paypal_app' => true,
            ],
        ], $payPalOrder['payment_source']['paypal']['experience_context']);
    }

    public function test_it_asks_paypal_to_complete_a_redirect_order_on_payment_approval(): void
    {
        $payPalOrder = $this->factory
            ->create($this->redirectPayment(), 'REFERENCE_ID', PayPalPaymentSourceProviderInterface::TRUSTLY, 'RETURN_NONCE', 'CANCEL_NONCE')
            ->toArray()
        ;

        self::assertSame('ORDER_COMPLETE_ON_PAYMENT_APPROVAL', $payPalOrder['processing_instruction']);
        self::assertArrayNotHasKey(
            'processing_instruction',
            $this->factory->create($this->payment, 'REFERENCE_ID')->toArray(),
        );
    }

    public function test_it_declares_no_shipping_callback_on_a_redirect_order(): void
    {
        $experienceContextProvider = $this->createMock(ExperienceContextProviderInterface::class);
        $experienceContextProvider
            ->expects(self::once())
            ->method('provide')
            ->with(self::anything(), self::anything(), self::anything(), null)
            ->willReturn([])
        ;

        (new PayPalOrderFactory(
            $this->payPalPurchaseUnitFactory,
            $this->router,
            $this->shippingCallbackUrlProvider,
            $experienceContextProvider,
        ))->create($this->redirectPayment(), 'REFERENCE_ID', PayPalPaymentSourceProviderInterface::TRUSTLY, 'RETURN_NONCE', 'CANCEL_NONCE');
    }

    public function test_it_still_declares_a_shipping_callback_on_a_wallet_order(): void
    {
        $experienceContextProvider = $this->createMock(ExperienceContextProviderInterface::class);
        $experienceContextProvider
            ->expects(self::once())
            ->method('provide')
            ->with(
                self::anything(),
                self::anything(),
                self::anything(),
                'https://shop.example.com/paypal/order-shipping-callback',
            )
            ->willReturn([])
        ;

        (new PayPalOrderFactory(
            $this->payPalPurchaseUnitFactory,
            $this->router,
            $this->shippingCallbackUrlProvider,
            $experienceContextProvider,
        ))->create($this->payment, 'REFERENCE_ID');
    }

    private function redirectPayment(): PaymentInterface&MockObject
    {
        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress->method('getFullName')->willReturn('Patrick Watson');
        $billingAddress->method('getCountryCode')->willReturn('NL');

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('patrick.watson@example.com');

        $order = $this->createMock(OrderInterface::class);
        $order->method('isShippingRequired')->willReturn(true);
        $order->method('getShippingAddress')->willReturn($this->createMock(AddressInterface::class));
        $order->method('getBillingAddress')->willReturn($billingAddress);
        $order->method('getCustomer')->willReturn($customer);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        return $payment;
    }

    public function test_it_sends_a_redirect_order_its_own_return_and_cancel_urls(): void
    {
        $experienceContextProvider = $this->createMock(ExperienceContextProviderInterface::class);
        $experienceContextProvider
            ->expects(self::once())
            ->method('provide')
            ->with(
                self::anything(),
                'https://shop.example.com/sylius_paypal_shop_redirect_return/RETURN_NONCE',
                'https://shop.example.com/sylius_paypal_shop_redirect_cancel/CANCEL_NONCE',
                null,
            )
            ->willReturn([])
        ;

        (new PayPalOrderFactory(
            $this->payPalPurchaseUnitFactory,
            $this->routeReflectingRouter(),
            $this->shippingCallbackUrlProvider,
            $experienceContextProvider,
        ))->create($this->redirectPayment(), 'REFERENCE_ID', PayPalPaymentSourceProviderInterface::TRUSTLY, 'RETURN_NONCE', 'CANCEL_NONCE');
    }

    public function test_it_refuses_to_build_a_redirect_order_without_a_payer_action_nonce(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->factory->create($this->redirectPayment(), 'REFERENCE_ID', PayPalPaymentSourceProviderInterface::TRUSTLY);
    }

    public function test_it_puts_no_payer_action_nonce_in_the_urls_of_a_wallet_order(): void
    {
        $experienceContextProvider = $this->createMock(ExperienceContextProviderInterface::class);
        $experienceContextProvider
            ->expects(self::once())
            ->method('provide')
            ->with(
                self::anything(),
                'https://shop.example.com/sylius_shop_checkout_complete',
                'https://shop.example.com/sylius_shop_checkout_complete',
                self::anything(),
            )
            ->willReturn([])
        ;

        (new PayPalOrderFactory(
            $this->payPalPurchaseUnitFactory,
            $this->routeReflectingRouter(),
            $this->shippingCallbackUrlProvider,
            $experienceContextProvider,
        ))->create($this->payment, 'REFERENCE_ID');
    }

    private function routeReflectingRouter(): UrlGeneratorInterface&MockObject
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters = []): string => rtrim(
                'https://shop.example.com/' . $route . '/' . ($parameters['nonce'] ?? ''),
                '/',
            ),
        );

        return $router;
    }

    public function test_it_builds_the_payment_source_through_its_provider(): void
    {
        $paymentSourceProvider = $this->createMock(PayPalPaymentSourceProviderInterface::class);
        $paymentSourceProvider
            ->expects(self::once())
            ->method('provide')
            ->with($this->order, 'google_pay', self::isType('array'))
            ->willReturn(['google_pay' => ['attributes' => []]])
        ;

        $payPalOrder = (new PayPalOrderFactory(
            $this->payPalPurchaseUnitFactory,
            $this->router,
            $this->shippingCallbackUrlProvider,
            null,
            $paymentSourceProvider,
        ))
            ->create($this->payment, 'REFERENCE_ID', 'google_pay')
            ->toArray()
        ;

        self::assertSame(['google_pay' => ['attributes' => []]], $payPalOrder['payment_source']);
    }

    private function purchaseUnit(): PayPalPurchaseUnit
    {
        return new PayPalPurchaseUnit(
            'REFERENCE_ID',
            'REFERENCE-NUMBER',
            'PLN',
            10000,
            1000,
            90.00,
            0.00,
            0,
            'merchant-id',
            [],
            true,
        );
    }
}
