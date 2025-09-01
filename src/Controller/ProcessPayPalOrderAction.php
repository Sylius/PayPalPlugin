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
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Abstraction\StateMachine\WinzouStateMachineAdapter;
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
use Sylius\PayPalPlugin\Exception\PaymentAmountMismatchException;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Verifier\PaymentAmountVerifierInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProcessPayPalOrderAction
{
    public function __construct(
        private readonly ?OrderRepositoryInterface $orderRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly FactoryInterface $customerFactory,
        private readonly AddressFactoryInterface $addressFactory,
        private readonly ObjectManager $orderManager,
        private readonly StateMachineFactoryInterface|StateMachineInterface $stateMachineFactory,
        private readonly PaymentStateManagerInterface $paymentStateManager,
        private readonly CacheAuthorizeClientApiInterface $authorizeClientApi,
        private readonly OrderDetailsApiInterface $orderDetailsApi,
        private readonly OrderProviderInterface $orderProvider,
        private readonly ?PaymentAmountVerifierInterface $paymentAmountVerifier = null,
    ) {
        if ($this->stateMachineFactory instanceof StateMachineFactoryInterface) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                message: sprintf(
                    'Passing an instance of "%s" as the sixth argument is deprecated and will be prohibited in 2.0. Use "%s" instead.',
                    StateMachineFactoryInterface::class,
                    StateMachineInterface::class,
                ),
            );
        }
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
        if (null !== $this->orderRepository) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.7',
                sprintf(
                    'Passing an instance of "%s" as the first argument is deprecated and will be prohibited in 2.0',
                    OrderRepositoryInterface::class,
                ),
            );
        }
    }

    public function __invoke(Request $request): Response
    {
        $orderId = $request->request->getInt('orderId');
        $order = $this->orderProvider->provideOrderById($orderId);

        /** @var PaymentInterface|null $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);

        if (null === $payment) {
            return new JsonResponse(['orderID' => $orderId]);
        }

        $data = $this->getOrderDetails((string) $request->request->get('payPalOrderId'), $payment);

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
            $address->setLastName(array_pop($name) ?? '');
            $address->setFirstName(implode(' ', $name));
            $address->setStreet($purchaseUnit['shipping']['address']['address_line_1']);
            $address->setCity($purchaseUnit['shipping']['address']['admin_area_2']);
            $address->setPostcode($purchaseUnit['shipping']['address']['postal_code']);
            $address->setCountryCode($purchaseUnit['shipping']['address']['country_code']);

            $this->getStateMachine()->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_ADDRESS);
            $this->getStateMachine()->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING);
        } else {
            $address->setFirstName($customer->getFirstName());
            $address->setLastName($customer->getLastName());

            $defaultAddress = $customer->getDefaultAddress();

            $address->setStreet($defaultAddress ? $defaultAddress->getStreet() : '');
            $address->setCity($defaultAddress ? $defaultAddress->getCity() : '');
            $address->setPostcode($defaultAddress ? $defaultAddress->getPostcode() : '');
            $address->setCountryCode($data['payer']['address']['country_code']);

            $this->getStateMachine()->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_ADDRESS);
        }

        $order->setShippingAddress(clone $address);
        $order->setBillingAddress(clone $address);

        $this->getStateMachine()->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);

        $this->orderManager->flush();

        try {
            if ($this->paymentAmountVerifier !== null) {
                $this->paymentAmountVerifier->verify($payment, $data);
            } else {
                $this->verify($payment, $data);
            }
        } catch (PaymentAmountMismatchException) {
            $this->paymentStateManager->cancel($payment);

            return new JsonResponse(['orderID' => $orderId]);
        }

        return new JsonResponse(['orderID' => $orderId]);
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

    private function getStateMachine(): StateMachineInterface
    {
        if ($this->stateMachineFactory instanceof StateMachineFactoryInterface) {
            return new WinzouStateMachineAdapter($this->stateMachineFactory);
        }

        return $this->stateMachineFactory;
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
