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

namespace Sylius\PayPalPlugin\Completer;

use Doctrine\Persistence\ObjectManager;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
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
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderApiInterface;
use Sylius\PayPalPlugin\Exception\PaymentAmountMismatchException;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Factory\ExpressOrderAddressFactoryInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Verifier\PaymentAmountVerifierInterface;
use Sylius\Resource\Factory\FactoryInterface;

final readonly class ExpressCheckoutCompleter implements ExpressCheckoutCompleterInterface
{
    /**
     * @param CustomerRepositoryInterface<CustomerInterface> $customerRepository
     * @param FactoryInterface<CustomerInterface> $customerFactory
     * @param ShippingMethodRepositoryInterface<ShippingMethodInterface> $shippingMethodRepository
     */
    public function __construct(
        private OrderDetailsApiInterface $orderDetailsApi,
        private UpdateOrderApiInterface $updateOrderApi,
        private CustomerRepositoryInterface $customerRepository,
        private FactoryInterface $customerFactory,
        private ExpressOrderAddressFactoryInterface $expressOrderAddressFactory,
        private ShippingMethodRepositoryInterface $shippingMethodRepository,
        private PaymentAmountVerifierInterface $paymentAmountVerifier,
        private StateMachineInterface $stateMachine,
        private OrderProcessorInterface $orderProcessor,
        private ObjectManager $orderManager,
    ) {
    }

    public function complete(PaymentInterface $payment): void
    {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        $payPalOrderId = (string) PayPalPaymentDetails::fromPayment($payment)->payPalOrderId();

        $payPalOrder = $this->orderDetailsApi->get($paymentMethod, $payPalOrderId);
        $payment->setDetails(PayPalPaymentDetails::fromPayment($payment)
            ->withPayPalOrderIntent($payPalOrder->intent())
            ->toArray());

        /** @var CustomerInterface|null $customer */
        $customer = $order->getCustomer();
        if (null === $customer) {
            $customer = $this->getOrderCustomer($payPalOrder);
            $order->setCustomer($customer);
        }

        if ($order->isShippingRequired()) {
            if (null === $order->getShippingAddress()) {
                $address = $this->expressOrderAddressFactory->createFromPayPalOrder($payPalOrder);

                $order->setShippingAddress(clone $address);
                $order->setBillingAddress(clone $address);
            }

            $this->applyCheckoutTransition($order, OrderCheckoutTransitions::TRANSITION_ADDRESS);
            $this->applyShippingMethodSelectedInWallet($order, $payPalOrder->selectedShippingOptionId());
            $this->applyCheckoutTransition($order, OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING);
        } else {
            if (null === $order->getShippingAddress() && null === $order->getBillingAddress()) {
                $address = $this->expressOrderAddressFactory->createFromCustomer(
                    $customer,
                    $payPalOrder->payerCountryCode(),
                    $payPalOrder->phoneNumber(),
                );

                $order->setShippingAddress(clone $address);
                $order->setBillingAddress(clone $address);
            }

            $this->applyCheckoutTransition($order, OrderCheckoutTransitions::TRANSITION_ADDRESS);
        }

        $this->applyCheckoutTransition($order, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
        $this->orderManager->flush();

        try {
            $this->paymentAmountVerifier->verify($payment, $payPalOrder);
        } catch (PaymentAmountMismatchException $exception) {
            if (!$this->isLowerThanApproved($order, $payPalOrder) || !$this->lowerApprovedAmount($payment, $payPalOrderId)) {
                $this->abandonPayment($order, $payment);

                throw $exception;
            }
        }

        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);
        $this->orderManager->flush();
    }

    private function applyCheckoutTransition(OrderInterface $order, string $transition): void
    {
        if ($this->stateMachine->can($order, OrderCheckoutTransitions::GRAPH, $transition)) {
            $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, $transition);
        }
    }

    private function applyShippingMethodSelectedInWallet(OrderInterface $order, ?string $selectedCode): void
    {
        if (null === $selectedCode) {
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

    private function isLowerThanApproved(OrderInterface $order, PayPalOrderDetails $payPalOrder): bool
    {
        return $order->getTotal() < ($payPalOrder->amount() ?? 0);
    }

    private function lowerApprovedAmount(PaymentInterface $payment, string $payPalOrderId): bool
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        try {
            $this->updateOrderApi->update(
                $paymentMethod,
                $payPalOrderId,
                $payment,
                (string) PayPalPaymentDetails::fromPayment($payment)->referenceId(),
                PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->merchantId(),
            );
        } catch (PayPalApiException) {
            return false;
        }

        return true;
    }

    private function abandonPayment(OrderInterface $order, PaymentInterface $payment): void
    {
        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);
        }

        $order->removePayment($payment);
        $this->orderProcessor->process($order);
        $this->orderManager->flush();
    }

    private function getOrderCustomer(PayPalOrderDetails $payPalOrder): CustomerInterface
    {
        /** @var CustomerInterface|null $existingCustomer */
        $existingCustomer = $this->customerRepository->findOneBy(['email' => $payPalOrder->payerEmail()]);
        if (null !== $existingCustomer) {
            return $existingCustomer;
        }

        $customer = $this->customerFactory->createNew();
        $customer->setEmail($payPalOrder->payerEmail());
        $customer->setFirstName($payPalOrder->payerGivenName());
        $customer->setLastName($payPalOrder->payerSurname());
        $customer->setPhoneNumber($payPalOrder->phoneNumber());

        return $customer;
    }
}
