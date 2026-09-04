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
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Exception\PaymentAmountMismatchException;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Verifier\PaymentAmountVerifierInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
            return new JsonResponse(['syliusOrderId' => $orderId, 'orderId' => $payPalOrderId]);
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

        if ($order->isShippingRequired()) {
            $name = explode(' ', $purchaseUnit['shipping']['name']['full_name']);
            /** @phpstan-ignore-next-line false positive */
            $address->setLastName(array_pop($name) ?? '');
            $address->setFirstName(implode(' ', $name));
            $address->setStreet($purchaseUnit['shipping']['address']['address_line_1']);
            $address->setCity($purchaseUnit['shipping']['address']['admin_area_2']);
            $address->setPostcode($purchaseUnit['shipping']['address']['postal_code']);
            $address->setCountryCode($purchaseUnit['shipping']['address']['country_code']);

            $order->setShippingAddress(clone $address);
            $order->setBillingAddress(clone $address);

            $this->stateMachineFactory->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_ADDRESS);

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
            $this->paymentStateManager->cancel($payment);

            return new JsonResponse(['syliusOrderId' => $orderId, 'orderId' => $payPalOrderId, 'status' => $payment->getState()]);
        }

        return new JsonResponse(['syliusOrderId' => $orderId, 'orderId' => $payPalOrderId, 'status' => $payment->getState()]);
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
