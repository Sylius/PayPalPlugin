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

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Completer\PayPalExpressOrderCompleterInterface;
use Sylius\PayPalPlugin\Exception\PaymentAmountMismatchException;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Resolver\PayPalShippingAddressResolverInterface;
use Sylius\PayPalPlugin\Verifier\PaymentAmountVerifierInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class ProcessPayPalOrderAction
{
    /**
     * @param CustomerRepositoryInterface<CustomerInterface> $customerRepository
     * @param FactoryInterface<CustomerInterface> $customerFactory
     * @param AddressFactoryInterface<AddressInterface> $addressFactory
     */
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private FactoryInterface $customerFactory,
        private AddressFactoryInterface $addressFactory,
        private ObjectManager $orderManager,
        private StateMachineInterface $stateMachineFactory,
        private PaymentStateManagerInterface $paymentStateManager,
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private OrderDetailsApiInterface $orderDetailsApi,
        private OrderProviderInterface $orderProvider,
        private ?PaymentAmountVerifierInterface $paymentAmountVerifier = null,
        private ?UrlGeneratorInterface $router = null,
        private ?PayPalExpressOrderCompleterInterface $orderCompleter = null,
        private ?OrderProcessorInterface $orderProcessor = null,
        private ?RepositoryInterface $shippingMethodRepository = null,
        private ?PayPalShippingAddressResolverInterface $shippingAddressResolver = null,
    ) {
        if (null === $this->paymentAmountVerifier) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                message: sprintf(
                    'Not passing $paymentAmountVerifier to "%s" constructor is deprecated and will be prohibited in 3.0',
                    self::class,
                ),
            );
        }
        if (null === $this->router) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing $router to "%s" constructor is deprecated and will be prohibited in 3.0',
                self::class,
            );
        }
        if (null === $this->orderCompleter) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing $orderCompleter to "%s" constructor is deprecated and will be prohibited in 3.0',
                self::class,
            );
        }
        if (null === $this->orderProcessor) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing $orderProcessor to "%s" constructor is deprecated and will be prohibited in 3.0',
                self::class,
            );
        }
        if (null === $this->shippingMethodRepository) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing $shippingMethodRepository to "%s" constructor is deprecated and will be prohibited in 3.0',
                self::class,
            );
        }
        if (null === $this->shippingAddressResolver) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing $shippingAddressResolver to "%s" constructor is deprecated and will be prohibited in 3.0',
                self::class,
            );
        }
    }

    public function __invoke(Request $request): Response
    {
        $payload = $request->getPayload();
        $orderId = $payload->getInt('orderId');
        $payPalOrderId = $payload->getString('payPalOrderId');

        $order = $this->orderProvider->provideOrderById($orderId);

        /** @var PaymentInterface|null $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);

        if (null === $payment) {
            $route = OrderCheckoutStates::STATE_COMPLETED === $order->getCheckoutState()
                ? 'sylius_shop_order_thank_you'
                : 'sylius_shop_checkout_complete';

            return new JsonResponse([
                'syliusOrderId' => $orderId,
                'orderId' => $payPalOrderId,
                'return_url' => $this->generateReturnUrl($route),
                'orderID' => $orderId, // BC with 2.0. Deprecated in 2.1; use "syliusOrderId" instead.
            ]);
        }

        if (($payment->getDetails()['paypal_order_id'] ?? null) !== $payPalOrderId) {
            return $this->returnToCheckout($orderId, $payPalOrderId, $payment, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $this->getOrderDetails($payPalOrderId, $payment);

        /** @var CustomerInterface|null $customer */
        $customer = $order->getCustomer();
        if ($customer === null) {
            $customer = $this->getOrderCustomer($data['payer']);
            $order->setCustomer($customer);
        }

        $purchaseUnit = (array) $data['purchase_units'][0];

        $address = $this->addressFactory->createNew();
        $address->setPhoneNumber($data['payer']['phone']['phone_number']['national_number'] ?? null);

        if ($order->isShippingRequired()) {
            $name = explode(' ', $purchaseUnit['shipping']['name']['full_name']);
            /** @phpstan-ignore-next-line false positive */
            $address->setLastName(array_pop($name) ?? '');
            $address->setFirstName(implode(' ', $name));
            $address->setStreet($purchaseUnit['shipping']['address']['address_line_1']);
            $address->setCity($purchaseUnit['shipping']['address']['admin_area_2']);
            $address->setPostcode($purchaseUnit['shipping']['address']['postal_code']);
            $address->setCountryCode($purchaseUnit['shipping']['address']['country_code']);
            $this->applyProvince($address, (array) $purchaseUnit['shipping']['address']);

            $order->setShippingAddress(clone $address);
            $order->setBillingAddress(clone $address);

            $this->stateMachineFactory->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_ADDRESS);

            $this->applyShippingMethodSelectedInWallet($order, $purchaseUnit);

            if ($this->stateMachineFactory->can($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING)) {
                $this->stateMachineFactory->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING);
            }
        } else {
            $address->setFirstName($customer->getFirstName());
            $address->setLastName($customer->getLastName());

            $defaultAddress = $customer->getDefaultAddress();

            $address->setStreet($defaultAddress ? $defaultAddress->getStreet() : '');
            $address->setCity($defaultAddress ? $defaultAddress->getCity() : '');
            $address->setPostcode($defaultAddress ? $defaultAddress->getPostcode() : '');
            $address->setCountryCode($data['payer']['address']['country_code']);

            $order->setShippingAddress(clone $address);
            $order->setBillingAddress(clone $address);

            $this->stateMachineFactory->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_ADDRESS);
        }

        if ($this->stateMachineFactory->can($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT)) {
            $this->stateMachineFactory->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
        }

        $this->orderManager->flush();

        try {
            if ($this->paymentAmountVerifier !== null) {
                $this->paymentAmountVerifier->verify($payment, $data);
            } else {
                $this->verify($payment, $data);
            }
        } catch (PaymentAmountMismatchException) {
            $this->abandonPayment($order, $payment);

            return $this->returnToCheckout($orderId, $payPalOrderId, $payment);
        }

        if (null === $this->orderCompleter) {
            throw new \RuntimeException('An order completer is required to complete the order.');
        }

        $this->orderCompleter->complete($order, $payment);

        $request->getSession()->set('sylius_order_id', $order->getId());

        return new JsonResponse([
            'syliusOrderId' => $orderId,
            'orderId' => $payPalOrderId,
            'status' => $payment->getState(),
            'return_url' => $this->generateReturnUrl('sylius_shop_order_thank_you'),
            'orderID' => $orderId, // BC with 2.0. Deprecated in 2.1; use "syliusOrderId" instead.
        ]);
    }

    private function returnToCheckout(
        int $orderId,
        string $payPalOrderId,
        PaymentInterface $payment,
        int $status = Response::HTTP_OK,
    ): JsonResponse {
        return new JsonResponse([
            'syliusOrderId' => $orderId,
            'orderId' => $payPalOrderId,
            'status' => $payment->getState(),
            'return_url' => $this->generateReturnUrl('sylius_shop_checkout_complete'),
            'orderID' => $orderId, // BC with 2.0. Deprecated in 2.1; use "syliusOrderId" instead.
        ], $status);
    }

    /** @param array<string, mixed> $purchaseUnit */
    private function applyShippingMethodSelectedInWallet(OrderInterface $order, array $purchaseUnit): void
    {
        if (null === $this->shippingMethodRepository) {
            return;
        }

        /** @var array<int, array<string, mixed>> $options */
        $options = (array) ($purchaseUnit['shipping']['options'] ?? []);

        $selectedCode = null;
        foreach ($options as $option) {
            if (true === ($option['selected'] ?? false)) {
                $selectedCode = (string) ($option['id'] ?? '');

                break;
            }
        }

        if (null === $selectedCode || '' === $selectedCode) {
            return;
        }

        $shipment = $order->getShipments()->first();
        if (!$shipment instanceof ShipmentInterface) {
            return;
        }

        $shippingMethod = $this->shippingMethodRepository->findOneBy(['code' => $selectedCode]);
        if ($shippingMethod instanceof ShippingMethodInterface) {
            $shipment->setMethod($shippingMethod);
        }
    }

    /** @param array<string, mixed> $payPalAddress */
    private function applyProvince(AddressInterface $address, array $payPalAddress): void
    {
        if (null === $this->shippingAddressResolver) {
            return;
        }

        $resolved = $this->shippingAddressResolver->resolve($payPalAddress);

        $address->setProvinceCode($resolved->getProvinceCode());
        $address->setProvinceName($resolved->getProvinceName());
    }

    private function abandonPayment(OrderInterface $order, PaymentInterface $payment): void
    {
        // The payment is still in the cart state here, where the payment state machine has no cancel transition.
        if ($this->stateMachineFactory->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL)) {
            $this->paymentStateManager->cancel($payment);
        }

        if (null === $this->orderProcessor) {
            throw new \RuntimeException('An order processor is required to process the order.');
        }

        $order->removePayment($payment);
        $this->orderProcessor->process($order);
        $this->orderManager->flush();
    }

    private function generateReturnUrl(string $route): string
    {
        if (null === $this->router) {
            throw new \RuntimeException('A router is required to generate the return URL.');
        }

        return $this->router->generate($route, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function getOrderCustomer(array $customerData): CustomerInterface
    {
        /** @var CustomerInterface|null $existingCustomer */
        $existingCustomer = $this->customerRepository->findOneBy(['email' => $customerData['email_address']]);
        if ($existingCustomer !== null) {
            return $existingCustomer;
        }

        /** @var CustomerInterface $customer */
        $customer = $this->customerFactory->createNew();
        $customer->setEmail($customerData['email_address']);
        $customer->setFirstName($customerData['name']['given_name']);
        $customer->setLastName($customerData['name']['surname']);
        $customer->setPhoneNumber($customerData['phone']['phone_number']['national_number'] ?? null);

        return $customer;
    }

    private function getOrderDetails(string $id, PaymentInterface $payment): array
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        $token = $this->authorizeClientApi->authorize($paymentMethod);

        return $this->orderDetailsApi->get($token, $id);
    }

    private function verify(PaymentInterface $payment, array $paypalOrderDetails): void
    {
        $totalAmount = $this->getTotalPaymentAmountFromPaypal($paypalOrderDetails);

        if ($payment->getAmount() !== $totalAmount) {
            throw new PaymentAmountMismatchException();
        }
    }

    private function getTotalPaymentAmountFromPaypal(array $paypalOrderDetails): int
    {
        if (!isset($paypalOrderDetails['purchase_units']) || !is_array($paypalOrderDetails['purchase_units'])) {
            return 0;
        }

        $totalAmount = 0;

        foreach ($paypalOrderDetails['purchase_units'] as $unit) {
            $stringAmount = $unit['amount']['value'] ?? '0';

            $totalAmount += (int) ($stringAmount * 100);
        }

        return $totalAmount;
    }
}
