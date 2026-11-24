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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Checker\PaymentAuthorizationCheckerInterface;
use Sylius\PayPalPlugin\Factory\PayPalOrderFactory;
use Sylius\PayPalPlugin\Factory\PayPalOrderFactoryInterface;
use Sylius\PayPalPlugin\Factory\PurchaseUnitFactoryInterface;
use Sylius\PayPalPlugin\Model\PayPalPurchaseUnit;
use Sylius\PayPalPlugin\Model\PayPalVaultRequest;
use Sylius\PayPalPlugin\Model\PayPalVaultRequirement;
use Sylius\PayPalPlugin\Provider\CurrentPayPalLocaleProviderInterface;
use Sylius\PayPalPlugin\Provider\ExperienceContextProvider;
use Sylius\PayPalPlugin\Provider\ExperienceContextProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProvider;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalVaultRequestProviderInterface;
use Sylius\PayPalPlugin\Provider\ShippingCallbackUrlProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PayPalOrderFactoryTest extends TestCase
{
    private PurchaseUnitFactoryInterface&MockObject $payPalPurchaseUnitFactory;

    private UrlGeneratorInterface&MockObject $router;

    private ShippingCallbackUrlProviderInterface&MockObject $shippingCallbackUrlProvider;

    private OrderInterface&MockObject $order;

    private PaymentInterface&MockObject $payment;

    private PayPalOrderFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payPalPurchaseUnitFactory = $this->createMock(PurchaseUnitFactoryInterface::class);
        $this->router = $this->createMock(UrlGeneratorInterface::class);
        $this->shippingCallbackUrlProvider = $this->createMock(ShippingCallbackUrlProviderInterface::class);
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

        $this->factory = $this->createFactory();
    }

    public function test_it_implements_paypal_order_factory_interface(): void
    {
        self::assertInstanceOf(PayPalOrderFactoryInterface::class, $this->factory);
    }

    public function test_it_captures_and_delegates_the_purchase_unit_to_its_own_factory(): void
    {
        $payPalPurchaseUnitFactory = $this->createMock(PurchaseUnitFactoryInterface::class);
        $payPalPurchaseUnitFactory
            ->expects(self::once())
            ->method('create')
            ->with($this->payment, 'REFERENCE_ID')
            ->willReturn($this->purchaseUnit())
        ;

        $payPalOrder = $this->createFactory(purchaseUnitFactory: $payPalPurchaseUnitFactory)
            ->create($this->payment, 'REFERENCE_ID')
            ->toArray()
        ;

        self::assertSame('CAPTURE', $payPalOrder['intent']);
        self::assertSame('REFERENCE_ID', $payPalOrder['purchase_units'][0]['reference_id']);
    }

    public function test_it_leaves_the_item_taxes_out_when_paypal_calls_back_with_the_address(): void
    {
        $payPalPurchaseUnitFactory = $this->createMock(PurchaseUnitFactoryInterface::class);
        $payPalPurchaseUnitFactory->expects(self::once())->method('create')->with($this->payment, 'REFERENCE_ID', null, false)->willReturn($this->purchaseUnit());

        $this->createFactory(purchaseUnitFactory: $payPalPurchaseUnitFactory)->create($this->payment, 'REFERENCE_ID');
    }

    public function test_it_sends_the_item_taxes_when_the_address_is_already_provided(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('isShippingRequired')->willReturn(true);
        $order->method('getShippingAddress')->willReturn($this->createMock(AddressInterface::class));
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $payPalPurchaseUnitFactory = $this->createMock(PurchaseUnitFactoryInterface::class);
        $payPalPurchaseUnitFactory->expects(self::once())->method('create')->with($payment, 'REFERENCE_ID', null, true)->willReturn($this->purchaseUnit());

        $this->createFactory(purchaseUnitFactory: $payPalPurchaseUnitFactory)->create($payment, 'REFERENCE_ID');
    }

    public function test_it_hands_the_custom_id_over_to_the_purchase_unit_factory(): void
    {
        $payPalPurchaseUnitFactory = $this->createMock(PurchaseUnitFactoryInterface::class);
        $payPalPurchaseUnitFactory->expects(self::once())->method('create')->with($this->payment, 'REFERENCE_ID', null, true, 'PAYMENT_REQUEST_HASH')->willReturn($this->purchaseUnit());

        $this->createFactory(purchaseUnitFactory: $payPalPurchaseUnitFactory, shippingCallbackUrlProvider: $this->shippingCallbackUrlProviderWithoutUrl())->create($this->payment, 'REFERENCE_ID', customId: 'PAYMENT_REQUEST_HASH');
    }

    public function test_it_sends_the_item_taxes_when_no_shipping_callback_is_declared(): void
    {
        $payPalPurchaseUnitFactory = $this->createMock(PurchaseUnitFactoryInterface::class);
        $payPalPurchaseUnitFactory->expects(self::once())->method('create')->with($this->payment, 'REFERENCE_ID', null, true)->willReturn($this->purchaseUnit());

        $this->createFactory(purchaseUnitFactory: $payPalPurchaseUnitFactory, shippingCallbackUrlProvider: $this->shippingCallbackUrlProviderWithoutUrl())->create($this->payment, 'REFERENCE_ID');
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
                'callback_events' => ['SHIPPING_ADDRESS', 'SHIPPING_OPTIONS'],
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

        $currentLocaleProvider = $this->createStub(CurrentPayPalLocaleProviderInterface::class);
        $currentLocaleProvider->method('provide')->willReturn('en_US');

        $experienceContext = $this->createFactory(experienceContextProvider: new ExperienceContextProvider($currentLocaleProvider))
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
        $experienceContext = $this->createFactory(shippingCallbackUrlProvider: $this->shippingCallbackUrlProviderWithoutUrl())
            ->create($this->payment, 'REFERENCE_ID')
            ->toArray()['payment_source']['paypal']['experience_context']
        ;

        self::assertArrayNotHasKey('order_update_callback_config', $experienceContext);
        self::assertSame('https://shop.example.com/checkout/complete', $experienceContext['return_url']);
    }

    public function test_it_asks_paypal_to_complete_a_redirect_order_on_payment_approval(): void
    {
        $payPalOrder = $this->factory
            ->create($this->redirectPayment(), 'REFERENCE_ID', PayPalPaymentSourceProviderInterface::TRUSTLY, returnUrl: 'https://shop.example.com/pay/HASH')
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

        $this->createFactory(experienceContextProvider: $experienceContextProvider)->create($this->redirectPayment(), 'REFERENCE_ID', PayPalPaymentSourceProviderInterface::TRUSTLY, returnUrl: 'https://shop.example.com/pay/HASH');
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

        $this->createFactory(experienceContextProvider: $experienceContextProvider)->create($this->payment, 'REFERENCE_ID');
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
        $order->method('getLocaleCode')->willReturn('en_US');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        return $payment;
    }

    public function test_it_refuses_to_build_a_redirect_order_without_a_url_to_come_back_to(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->factory->create($this->redirectPayment(), 'REFERENCE_ID', PayPalPaymentSourceProviderInterface::TRUSTLY);
    }

    public function test_it_sends_the_payer_of_a_wallet_order_back_to_the_checkout_by_default(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getLocaleCode')->willReturn('en_US');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $experienceContextProvider = $this->createMock(ExperienceContextProviderInterface::class);
        $experienceContextProvider
            ->expects(self::once())
            ->method('provide')
            ->with(
                self::anything(),
                'https://shop.example.com/en_US/sylius_shop_checkout_complete',
                'https://shop.example.com/en_US/sylius_shop_checkout_complete',
                self::anything(),
            )
            ->willReturn([])
        ;

        $this->createFactory(router: $this->routeReflectingRouter(), experienceContextProvider: $experienceContextProvider)->create($payment, 'REFERENCE_ID');
    }

    public function test_it_sends_the_payer_back_to_the_return_url_it_is_given(): void
    {
        $experienceContextProvider = $this->createMock(ExperienceContextProviderInterface::class);
        $experienceContextProvider
            ->expects(self::once())
            ->method('provide')
            ->with(self::anything(), 'https://shop.example.com/en_US/payment-request/pay/HASH', 'https://shop.example.com/en_US/payment-request/pay/HASH', null)
            ->willReturn([])
        ;

        $this->createFactory(router: $this->routeReflectingRouter(), experienceContextProvider: $experienceContextProvider)->create($this->redirectPayment(), 'REFERENCE_ID', PayPalPaymentSourceProviderInterface::TRUSTLY, returnUrl: 'https://shop.example.com/en_US/payment-request/pay/HASH');
    }

    public function test_it_sends_the_payer_who_cancels_to_the_cancel_url_it_is_given(): void
    {
        $experienceContextProvider = $this->createMock(ExperienceContextProviderInterface::class);
        $experienceContextProvider
            ->expects(self::once())
            ->method('provide')
            ->with(self::anything(), 'https://shop.example.com/pay/HASH', 'https://shop.example.com/pay/HASH?payer_cancelled=1', null)
            ->willReturn([])
        ;

        $this->createFactory(router: $this->routeReflectingRouter(), experienceContextProvider: $experienceContextProvider)->create($this->redirectPayment(), 'REFERENCE_ID', PayPalPaymentSourceProviderInterface::TRUSTLY, returnUrl: 'https://shop.example.com/pay/HASH', cancelUrl: 'https://shop.example.com/pay/HASH?payer_cancelled=1');
    }

    private function routeReflectingRouter(): UrlGeneratorInterface&MockObject
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters = []): string => rtrim(
                'https://shop.example.com/' . $parameters['_locale'] . '/' . $route,
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
            ->with($this->payment, 'google_pay', self::isType('array'))
            ->willReturn(['google_pay' => ['attributes' => []]])
        ;

        $payPalOrder = $this->createFactory(paymentSourceProvider: $paymentSourceProvider)
            ->create($this->payment, 'REFERENCE_ID', 'google_pay')
            ->toArray()
        ;

        self::assertSame(['google_pay' => ['attributes' => []]], $payPalOrder['payment_source']);
    }

    public function test_it_authorizes_when_the_payment_method_authorizes_the_payment_source(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->payment->method('getMethod')->willReturn($paymentMethod);

        $paymentAuthorizationChecker = $this->createMock(PaymentAuthorizationCheckerInterface::class);
        $paymentAuthorizationChecker->expects(self::once())->method('isAuthorizable')->with('card', $paymentMethod)->willReturn(true);

        $payPalOrder = $this->factoryCheckingAuthorizationWith($paymentAuthorizationChecker)
            ->create($this->payment, 'REFERENCE_ID', 'card')
            ->toArray()
        ;

        self::assertSame('AUTHORIZE', $payPalOrder['intent']);
    }

    public function test_it_captures_when_the_payment_method_does_not_authorize_the_payment_source(): void
    {
        $this->payment->method('getMethod')->willReturn($this->createMock(PaymentMethodInterface::class));

        $paymentAuthorizationChecker = $this->createMock(PaymentAuthorizationCheckerInterface::class);
        $paymentAuthorizationChecker->method('isAuthorizable')->willReturn(false);

        $payPalOrder = $this->factoryCheckingAuthorizationWith($paymentAuthorizationChecker)
            ->create($this->payment, 'REFERENCE_ID')
            ->toArray()
        ;

        self::assertSame('CAPTURE', $payPalOrder['intent']);
    }

    public function test_it_captures_a_payment_without_a_payment_method(): void
    {
        $this->payment->method('getMethod')->willReturn(null);

        $paymentAuthorizationChecker = $this->createMock(PaymentAuthorizationCheckerInterface::class);
        $paymentAuthorizationChecker->expects(self::never())->method('isAuthorizable');

        $payPalOrder = $this->factoryCheckingAuthorizationWith($paymentAuthorizationChecker)
            ->create($this->payment, 'REFERENCE_ID')
            ->toArray()
        ;

        self::assertSame('CAPTURE', $payPalOrder['intent']);
    }

    private function factoryCheckingAuthorizationWith(PaymentAuthorizationCheckerInterface $paymentAuthorizationChecker): PayPalOrderFactory
    {
        return $this->createFactory(paymentAuthorizationChecker: $paymentAuthorizationChecker);
    }

    private function createFactory(
        ?PurchaseUnitFactoryInterface $purchaseUnitFactory = null,
        ?UrlGeneratorInterface $router = null,
        ?ShippingCallbackUrlProviderInterface $shippingCallbackUrlProvider = null,
        ?ExperienceContextProviderInterface $experienceContextProvider = null,
        ?PayPalPaymentSourceProviderInterface $paymentSourceProvider = null,
        ?PaymentAuthorizationCheckerInterface $paymentAuthorizationChecker = null,
        ?PayPalVaultRequestProviderInterface $vaultRequestProvider = null,
    ): PayPalOrderFactory {
        return new PayPalOrderFactory(
            $purchaseUnitFactory ?? $this->payPalPurchaseUnitFactory,
            $router ?? $this->router,
            $shippingCallbackUrlProvider ?? $this->shippingCallbackUrlProvider,
            $experienceContextProvider ?? new ExperienceContextProvider($this->createStub(CurrentPayPalLocaleProviderInterface::class)),
            $paymentSourceProvider ?? new PayPalPaymentSourceProvider(),
            $paymentAuthorizationChecker ?? $this->createStub(PaymentAuthorizationCheckerInterface::class),
            $vaultRequestProvider,
        );
    }

    private function shippingCallbackUrlProviderWithoutUrl(): ShippingCallbackUrlProviderInterface
    {
        $shippingCallbackUrlProvider = $this->createStub(ShippingCallbackUrlProviderInterface::class);
        $shippingCallbackUrlProvider->method('provide')->willReturn(null);

        return $shippingCallbackUrlProvider;
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

    #[DataProvider('vaultablePaymentSources')]
    public function test_it_lets_the_items_of_an_order_paid_with_a_vaultable_payment_source_recur_on_the_vault_request(string $paymentSource): void
    {
        $vaultRequest = new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID);
        $vaultRequestProvider = $this->createStub(PayPalVaultRequestProviderInterface::class);
        $vaultRequestProvider->method('provide')->willReturn(PayPalVaultRequirement::met($vaultRequest));

        self::assertSame($vaultRequest, $this->vaultRequestHandedToThePurchaseUnit($vaultRequestProvider, $paymentSource));
    }

    /** @return iterable<string, array{string}> */
    public static function vaultablePaymentSources(): iterable
    {
        yield 'wallet' => [PayPalPaymentSourceProviderInterface::PAYPAL];
        yield 'card' => [PayPalPaymentSourceProviderInterface::CARD];
    }

    public function test_it_resolves_the_vault_request_once_and_hands_it_to_both_the_items_and_the_payment_source(): void
    {
        $vaultRequest = new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID);
        $vaultRequestProvider = $this->createMock(PayPalVaultRequestProviderInterface::class);
        $vaultRequestProvider->expects(self::once())->method('provide')->willReturn(PayPalVaultRequirement::met($vaultRequest));

        $paymentSourceProvider = $this->createMock(PayPalPaymentSourceProviderInterface::class);
        $paymentSourceProvider
            ->expects(self::once())
            ->method('provide')
            ->willReturnCallback(static function (...$arguments) use ($vaultRequest): array {
                self::assertSame($vaultRequest, $arguments[3] ?? null);

                return [];
            })
        ;

        self::assertSame(
            $vaultRequest,
            $this->vaultRequestHandedToThePurchaseUnit($vaultRequestProvider, PayPalPaymentSourceProviderInterface::PAYPAL, $paymentSourceProvider),
        );
    }

    public function test_it_tells_the_payment_source_there_is_no_vault_request_on_an_order_paid_otherwise(): void
    {
        $paymentSourceProvider = $this->createMock(PayPalPaymentSourceProviderInterface::class);
        $paymentSourceProvider
            ->expects(self::once())
            ->method('provide')
            ->willReturnCallback(static function (...$arguments): array {
                self::assertCount(4, $arguments);
                self::assertNull($arguments[3]);

                return [];
            })
        ;

        $this->vaultRequestHandedToThePurchaseUnit($this->createStub(PayPalVaultRequestProviderInterface::class), PayPalPaymentSourceProviderInterface::VENMO, $paymentSourceProvider);
    }

    public function test_it_lets_no_item_recur_on_an_order_paid_with_a_payment_source_that_cannot_be_vaulted(): void
    {
        $vaultRequestProvider = $this->createMock(PayPalVaultRequestProviderInterface::class);
        $vaultRequestProvider->expects(self::never())->method('provide');

        self::assertNull($this->vaultRequestHandedToThePurchaseUnit($vaultRequestProvider, PayPalPaymentSourceProviderInterface::VENMO));
    }

    public function test_it_lets_the_payment_source_resolve_the_vault_request_itself_without_its_own_vault_request_provider(): void
    {
        $paymentSourceProvider = $this->createMock(PayPalPaymentSourceProviderInterface::class);
        $paymentSourceProvider
            ->expects(self::once())
            ->method('provide')
            ->willReturnCallback(static function (...$arguments): array {
                self::assertCount(3, $arguments);

                return [];
            })
        ;

        $this->createFactory(paymentSourceProvider: $paymentSourceProvider)->create($this->payment, 'REFERENCE_ID');
    }

    private function vaultRequestHandedToThePurchaseUnit(
        PayPalVaultRequestProviderInterface $vaultRequestProvider,
        string $paymentSource,
        ?PayPalPaymentSourceProviderInterface $paymentSourceProvider = null,
    ): ?PayPalVaultRequest {
        $handedVaultRequest = null;
        $payPalPurchaseUnitFactory = $this->createMock(PurchaseUnitFactoryInterface::class);
        $payPalPurchaseUnitFactory
            ->method('create')
            ->willReturnCallback(function (...$arguments) use (&$handedVaultRequest): PayPalPurchaseUnit {
                $handedVaultRequest = $arguments[5] ?? null;

                return $this->purchaseUnit();
            })
        ;

        $this->createFactory($payPalPurchaseUnitFactory, paymentSourceProvider: $paymentSourceProvider ?? $this->createStub(PayPalPaymentSourceProviderInterface::class), vaultRequestProvider: $vaultRequestProvider)
            ->create($this->payment, 'REFERENCE_ID', $paymentSource)
        ;

        return $handedVaultRequest;
    }
}
