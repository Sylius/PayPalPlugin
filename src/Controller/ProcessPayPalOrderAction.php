<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProcessPayPalOrderAction
{
    private OrderRepositoryInterface $orderRepository;

    private CustomerRepositoryInterface $customerRepository;

    private \Sylius\Component\Resource\Factory\FactoryInterface $customerFactory;

    private AddressFactoryInterface $addressFactory;

    private ObjectManager $orderManager;

    private StateMachineFactoryInterface $stateMachineFactory;

    private PaymentStateManagerInterface $paymentStateManager;

    private CacheAuthorizeClientApiInterface $authorizeClientApi;

    private OrderDetailsApiInterface $orderDetailsApi;

    private OrderProviderInterface $orderProvider;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CustomerRepositoryInterface $customerRepository,
        FactoryInterface $customerFactory,
        AddressFactoryInterface $addressFactory,
        ObjectManager $orderManager,
        StateMachineFactoryInterface $stateMachineFactory,
        PaymentStateManagerInterface $paymentStateManager,
        CacheAuthorizeClientApiInterface $authorizeClientApi,
        OrderDetailsApiInterface $orderDetailsApi,
        OrderProviderInterface $orderProvider
    ) {
        $this->orderRepository = $orderRepository;
        $this->customerRepository = $customerRepository;
        $this->customerFactory = $customerFactory;
        $this->addressFactory = $addressFactory;
        $this->orderManager = $orderManager;
        $this->stateMachineFactory = $stateMachineFactory;
        $this->paymentStateManager = $paymentStateManager;
        $this->authorizeClientApi = $authorizeClientApi;
        $this->orderDetailsApi = $orderDetailsApi;
        $this->orderProvider = $orderProvider;
    }

    public function __invoke(Request $request): Response
    {
        $orderId = $request->request->getInt('orderId');
        $order = $this->orderProvider->provideOrderById($orderId);
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);

        $data = $this->getOrderDetails((string) $request->request->get('payPalOrderId'), $payment);

        /** @var CustomerInterface|null $customer */
        $customer = $order->getCustomer();
        if ($customer === null) {
            $customer = $this->getOrderCustomer($data['payer']);
            $order->setCustomer($customer);
        }

        $purchaseUnit = (array) $data['purchase_units'][0];
        $stateMachine = $this->stateMachineFactory->get($order, OrderCheckoutTransitions::GRAPH);

        $address = $this->addressFactory->createForCustomer($customer);

        if ($order->isShippingRequired()) {
            $name = explode(' ', $purchaseUnit['shipping']['name']['full_name']);
            $address->setFirstName($name[0]);
            $address->setLastName($name[1]);
            $address->setStreet($purchaseUnit['shipping']['address']['address_line_1']);
            $address->setCity($purchaseUnit['shipping']['address']['admin_area_2']);
            $address->setPostcode($purchaseUnit['shipping']['address']['postal_code']);
            $address->setCountryCode($purchaseUnit['shipping']['address']['country_code']);

            $stateMachine->apply(OrderCheckoutTransitions::TRANSITION_ADDRESS);
            $stateMachine->apply(OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING);
        } else {
            $address->setFirstName($customer->getFirstName());
            $address->setLastName($customer->getLastName());

            $defaultAddress = $customer->getDefaultAddress();

            $address->setStreet($defaultAddress ? $defaultAddress->getStreet() : '');
            $address->setCity($defaultAddress ? $defaultAddress->getCity() : '');
            $address->setPostcode($defaultAddress ? $defaultAddress->getPostcode() : '');
            $address->setCountryCode($data['payer']['address']['country_code']);
            $stateMachine->apply(OrderCheckoutTransitions::TRANSITION_ADDRESS);
        }

        $order->setShippingAddress(clone $address);
        $order->setBillingAddress(clone $address);

        $stateMachine->apply(OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);

        $this->orderManager->flush();

        $this->paymentStateManager->create($payment);
        $this->paymentStateManager->process($payment);

        return new JsonResponse(['orderID' => $order->getId()]);
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

        return $customer;
    }

    private function getOrderDetails(string $id, PaymentInterface $payment): array
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        $token = $this->authorizeClientApi->authorize($paymentMethod);

        return $this->orderDetailsApi->get($token, $id);
    }
}
