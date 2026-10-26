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

namespace Tests\Sylius\PayPalPlugin\Unit\Completer;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Core\Repository\ShippingMethodRepositoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderApiInterface;
use Sylius\PayPalPlugin\Completer\ExpressCheckoutCompleter;
use Sylius\PayPalPlugin\Exception\PaymentAmountMismatchException;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Factory\ExpressOrderAddressFactoryInterface;
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Verifier\PaymentAmountVerifierInterface;
use Sylius\Resource\Factory\FactoryInterface;

final class ExpressCheckoutCompleterTest extends TestCase
{
    private OrderDetailsApiInterface&MockObject $orderDetailsApi;

    private UpdateOrderApiInterface&MockObject $updateOrderApi;

    /** @var CustomerRepositoryInterface<CustomerInterface>&MockObject */
    private CustomerRepositoryInterface&MockObject $customerRepository;

    /** @var FactoryInterface<CustomerInterface>&MockObject */
    private FactoryInterface&MockObject $customerFactory;

    private ExpressOrderAddressFactoryInterface&MockObject $expressOrderAddressFactory;

    /** @var ShippingMethodRepositoryInterface<ShippingMethodInterface>&MockObject */
    private ShippingMethodRepositoryInterface&MockObject $shippingMethodRepository;

    private PaymentAmountVerifierInterface&MockObject $paymentAmountVerifier;

    private StateMachineInterface&MockObject $stateMachine;

    private OrderProcessorInterface&MockObject $orderProcessor;

    private ObjectManager&MockObject $orderManager;

    private OrderInterface&MockObject $order;

    private PaymentInterface&MockObject $payment;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private ExpressCheckoutCompleter $completer;

    /** @var list<string> */
    private array $appliedCheckoutTransitions = [];

    protected function setUp(): void
    {
        $this->orderDetailsApi = $this->createMock(OrderDetailsApiInterface::class);
        $this->updateOrderApi = $this->createMock(UpdateOrderApiInterface::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->customerFactory = $this->createMock(FactoryInterface::class);
        $this->expressOrderAddressFactory = $this->createMock(ExpressOrderAddressFactoryInterface::class);
        $this->shippingMethodRepository = $this->createMock(ShippingMethodRepositoryInterface::class);
        $this->paymentAmountVerifier = $this->createMock(PaymentAmountVerifierInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->orderProcessor = $this->createMock(OrderProcessorInterface::class);
        $this->orderManager = $this->createMock(ObjectManager::class);

        $this->order = $this->createMock(OrderInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->payment = $this->createMock(PaymentInterface::class);
        $this->payment->method('getOrder')->willReturn($this->order);
        $this->payment->method('getMethod')->willReturn($this->paymentMethod);
        $this->payment->method('getDetails')->willReturn(
            PayPalPaymentDetails::create()->withPayPalOrderId('PAYPAL-ORDER')->withReferenceId('REFERENCE')->toArray(),
        );

        $this->stateMachine->method('can')->willReturn(true);
        $this->stateMachine->method('apply')->willReturnCallback(function (object $subject, string $graph, string $transition): void {
            if (OrderCheckoutTransitions::GRAPH === $graph) {
                $this->appliedCheckoutTransitions[] = $transition;
            }
        });

        $this->completer = new ExpressCheckoutCompleter(
            $this->orderDetailsApi,
            $this->updateOrderApi,
            $this->customerRepository,
            $this->customerFactory,
            $this->expressOrderAddressFactory,
            $this->shippingMethodRepository,
            $this->paymentAmountVerifier,
            $this->stateMachine,
            $this->orderProcessor,
            $this->orderManager,
        );
    }

    public function test_it_places_the_order_with_the_customer_address_and_shipping_method_from_the_wallet(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $address = $this->createMock(AddressInterface::class);
        $shipment = $this->createMock(ShipmentInterface::class);
        $shippingMethod = $this->createMock(ShippingMethodInterface::class);

        $this->orderDetailsApi->expects(self::once())->method('get')->with($this->paymentMethod, 'PAYPAL-ORDER')->willReturn($this->payPalOrder());
        $this->order->method('getCustomer')->willReturn(null);
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn(null);
        $this->order->method('getShipments')->willReturn(new ArrayCollection([$shipment]));
        $this->customerRepository->method('findOneBy')->with(['email' => 'buyer@example.com'])->willReturn($customer);
        $this->expressOrderAddressFactory->expects(self::once())->method('createFromPayPalOrder')->with($this->payPalOrder())->willReturn($address);
        $this->shippingMethodRepository->method('findOneBy')->with(['code' => 'DHL'])->willReturn($shippingMethod);

        $this->order->expects(self::once())->method('setCustomer')->with($customer);
        $this->order->expects(self::once())->method('setShippingAddress');
        $this->order->expects(self::once())->method('setBillingAddress');
        $shipment->expects(self::once())->method('setMethod')->with($shippingMethod);
        $this->updateOrderApi->expects(self::never())->method('update');
        $this->orderManager->expects(self::exactly(2))->method('flush');
        $this->completer->complete($this->payment);

        self::assertSame([
            OrderCheckoutTransitions::TRANSITION_ADDRESS,
            OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING,
            OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT,
            OrderCheckoutTransitions::TRANSITION_COMPLETE,
        ], $this->appliedCheckoutTransitions);
    }

    public function test_it_creates_a_customer_from_the_payer_when_none_has_their_email(): void
    {
        $customer = $this->createMock(CustomerInterface::class);

        $this->orderDetailsApi->method('get')->willReturn($this->payPalOrder());
        $this->order->method('getCustomer')->willReturn(null);
        $this->order->method('getShippingAddress')->willReturn($this->createMock(AddressInterface::class));
        $this->order->method('getShipments')->willReturn(new ArrayCollection());
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->customerRepository->method('findOneBy')->willReturn(null);
        $this->customerFactory->method('createNew')->willReturn($customer);

        $customer->expects(self::once())->method('setEmail')->with('buyer@example.com');
        $customer->expects(self::once())->method('setFirstName')->with('John');
        $customer->expects(self::once())->method('setLastName')->with('Doe');
        $customer->expects(self::once())->method('setPhoneNumber')->with('2029790941');
        $this->order->expects(self::once())->method('setCustomer')->with($customer);

        $this->completer->complete($this->payment);
    }

    public function test_it_keeps_the_customer_and_address_the_order_already_has(): void
    {
        $this->orderDetailsApi->method('get')->willReturn($this->payPalOrder());
        $this->order->method('getCustomer')->willReturn($this->createMock(CustomerInterface::class));
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn($this->createMock(AddressInterface::class));
        $this->order->method('getShipments')->willReturn(new ArrayCollection());

        $this->order->expects(self::never())->method('setCustomer');
        $this->order->expects(self::never())->method('setShippingAddress');
        $this->expressOrderAddressFactory->expects(self::never())->method('createFromPayPalOrder');

        $this->completer->complete($this->payment);
    }

    public function test_it_addresses_an_order_without_shipping_from_the_payer_country(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $address = $this->createMock(AddressInterface::class);

        $this->orderDetailsApi->method('get')->willReturn($this->payPalOrder(null, '5551234'));
        $this->order->method('getCustomer')->willReturn($customer);
        $this->order->method('isShippingRequired')->willReturn(false);
        $this->order->method('getShippingAddress')->willReturn(null);
        $this->order->method('getBillingAddress')->willReturn(null);

        $this->expressOrderAddressFactory->expects(self::once())->method('createFromCustomer')->with($customer, 'US', '5551234')->willReturn($address);
        $this->order->expects(self::once())->method('setBillingAddress');

        $this->completer->complete($this->payment);

        self::assertSame([
            OrderCheckoutTransitions::TRANSITION_ADDRESS,
            OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT,
            OrderCheckoutTransitions::TRANSITION_COMPLETE,
        ], $this->appliedCheckoutTransitions);
    }

    public function test_it_ignores_a_wallet_shipping_method_the_shop_does_not_know(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);

        $this->orderDetailsApi->method('get')->willReturn($this->payPalOrder());
        $this->order->method('getCustomer')->willReturn($this->createMock(CustomerInterface::class));
        $this->order->method('isShippingRequired')->willReturn(true);
        $this->order->method('getShippingAddress')->willReturn($this->createMock(AddressInterface::class));
        $this->order->method('getShipments')->willReturn(new ArrayCollection([$shipment]));
        $this->shippingMethodRepository->method('findOneBy')->willReturn(null);

        $shipment->expects(self::never())->method('setMethod');

        $this->completer->complete($this->payment);
    }

    public function test_it_stores_the_intent_the_approved_paypal_order_was_created_with(): void
    {
        $this->givenAnAddressedOrderWithTotal(10000);

        $this->payment->expects(self::once())->method('setDetails')->with(PayPalPaymentDetails::create()->withPayPalOrderId('PAYPAL-ORDER')->withReferenceId('REFERENCE')->withPayPalOrderIntent('AUTHORIZE')->toArray());

        $this->completer->complete($this->payment);
    }

    public function test_it_lowers_the_approved_amount_when_the_order_total_dropped(): void
    {
        $this->givenAnAddressedOrderWithTotal(9000);
        $this->paymentAmountVerifier->method('verify')->willThrowException(new PaymentAmountMismatchException());
        $this->paymentMethod->method('getGatewayConfig')->willReturn($this->gatewayConfig());

        $this->updateOrderApi->expects(self::once())->method('update')->with($this->paymentMethod, 'PAYPAL-ORDER', $this->payment, 'REFERENCE', 'MERCHANT');
        $this->order->expects(self::never())->method('removePayment');

        $this->completer->complete($this->payment);

        self::assertSame([
            OrderCheckoutTransitions::TRANSITION_ADDRESS,
            OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT,
            OrderCheckoutTransitions::TRANSITION_COMPLETE,
        ], $this->appliedCheckoutTransitions);
    }

    public function test_it_abandons_the_payment_without_placing_the_order_when_the_order_total_grew(): void
    {
        $this->givenAnAddressedOrderWithTotal(11000);
        $this->paymentAmountVerifier->method('verify')->willThrowException(new PaymentAmountMismatchException());

        $this->updateOrderApi->expects(self::never())->method('update');
        $this->order->expects(self::once())->method('removePayment')->with($this->payment);
        $this->orderProcessor->expects(self::once())->method('process')->with($this->order);

        try {
            $this->completer->complete($this->payment);
            self::fail('The order total change was not reported.');
        } catch (PaymentAmountMismatchException) {
        }

        self::assertSame([
            OrderCheckoutTransitions::TRANSITION_ADDRESS,
            OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT,
        ], $this->appliedCheckoutTransitions);
    }

    public function test_it_abandons_the_payment_when_paypal_refuses_to_lower_the_approved_amount(): void
    {
        $this->givenAnAddressedOrderWithTotal(9000);
        $this->paymentAmountVerifier->method('verify')->willThrowException(new PaymentAmountMismatchException());
        $this->paymentMethod->method('getGatewayConfig')->willReturn($this->gatewayConfig());
        $this->updateOrderApi->method('update')->willThrowException(new PayPalApiRequestException('PATCH', 'v2/checkout/orders/PAYPAL-ORDER', 422));

        $this->order->expects(self::once())->method('removePayment')->with($this->payment);

        $this->expectException(PaymentAmountMismatchException::class);

        $this->completer->complete($this->payment);
    }

    private function givenAnAddressedOrderWithTotal(int $total): void
    {
        $this->orderDetailsApi->method('get')->willReturn($this->payPalOrder());
        $this->order->method('getCustomer')->willReturn($this->createMock(CustomerInterface::class));
        $this->order->method('isShippingRequired')->willReturn(false);
        $this->order->method('getShippingAddress')->willReturn($this->createMock(AddressInterface::class));
        $this->order->method('getTotal')->willReturn($total);
    }

    private function gatewayConfig(): GatewayConfigInterface
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn(['merchant_id' => 'MERCHANT']);

        return $gatewayConfig;
    }

    private function payPalOrder(?string $shippingPhoneNumber = '2029790941', ?string $payerPhoneNumber = null): PayPalOrderDetails
    {
        $payPalOrder = [
            'id' => 'PAYPAL-ORDER',
            'intent' => 'AUTHORIZE',
            'payer' => [
                'email_address' => 'buyer@example.com',
                'name' => ['given_name' => 'John', 'surname' => 'Doe'],
                'address' => ['country_code' => 'US'],
            ],
            'purchase_units' => [[
                'reference_id' => 'REFERENCE',
                'amount' => ['currency_code' => 'USD', 'value' => '100.00'],
                'shipping' => [
                    'options' => [
                        ['id' => 'UPS', 'selected' => false],
                        ['id' => 'DHL', 'selected' => true],
                    ],
                ],
            ]],
        ];

        if (null !== $shippingPhoneNumber) {
            $payPalOrder['purchase_units'][0]['shipping']['phone_number'] = ['country_code' => '1', 'national_number' => $shippingPhoneNumber];
        }

        if (null !== $payerPhoneNumber) {
            $payPalOrder['payer']['phone'] = ['phone_number' => ['national_number' => $payerPhoneNumber]];
        }

        return PayPalOrderDetails::fromArray($payPalOrder);
    }
}
