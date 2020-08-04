<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller;

use Doctrine\Persistence\ObjectManager;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProcessPayPalOrderAction
{
    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var CustomerRepositoryInterface */
    private $customerRepository;

    /** @var FactoryInterface */
    private $customerFactory;

    /** @var AddressFactoryInterface */
    private $addressFactory;

    /** @var ObjectManager */
    private $orderManager;

    /** @var StateMachineFactoryInterface */
    private $stateMachineFactory;

    /** @var PaymentStateManagerInterface */
    private $paymentStateManager;

    /** @var AuthorizeClientApiInterface */
    private $authorizeClientApi;

    /** @var OrderDetailsApiInterface */
    private $orderDetailsApi;

    /** @var OrderProviderInterface */
    private $orderProvider;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CustomerRepositoryInterface $customerRepository,
        FactoryInterface $customerFactory,
        AddressFactoryInterface $addressFactory,
        ObjectManager $orderManager,
        StateMachineFactoryInterface $stateMachineFactory,
        PaymentStateManagerInterface $paymentStateManager,
        AuthorizeClientApiInterface $authorizeClientApi,
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

        $data = $this->getOrderDetails($request->request->get('payPalOrderId'), $payment);

        $customer = $this->getOrderCustomer($data['payer']);
        $order->setCustomer($customer);

        $purchaseUnit = (array) $data['purchase_units'][0];

        $address = $this->addressFactory->createForCustomer($customer);
        $name = explode(' ', $purchaseUnit['shipping']['name']['full_name']);
        $address->setFirstName($name[0]);
        $address->setLastName($name[1]);
        $address->setStreet($purchaseUnit['shipping']['address']['address_line_1']);
        $address->setCity($purchaseUnit['shipping']['address']['admin_area_2']);
        $address->setPostcode($purchaseUnit['shipping']['address']['postal_code']);
        $address->setCountryCode($purchaseUnit['shipping']['address']['country_code']);

        $order->setShippingAddress(clone $address);
        $order->setBillingAddress(clone $address);

        $stateMachine = $this->stateMachineFactory->get($order, OrderCheckoutTransitions::GRAPH);
        $stateMachine->apply(OrderCheckoutTransitions::TRANSITION_ADDRESS);
        $stateMachine->apply(OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING);
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
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = $gatewayConfig->getConfig();

        $token = $this->authorizeClientApi->authorize($config['client_id'], $config['client_secret']);

        return $this->orderDetailsApi->get($token, $id);
    }
}
