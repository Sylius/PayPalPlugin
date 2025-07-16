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
use GuzzleHttp\Exception\GuzzleException;
use Payum\Core\Payum;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Payment\Remover\OrderPaymentsRemoverInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final class CreatePayPalOrderFromCartAction
{
    public function __construct(
        private readonly ?Payum $payum,
        private readonly ?OrderRepositoryInterface $orderRepository,
        private readonly ?FactoryInterface $stateMachineFactory,
        private readonly ObjectManager $paymentManager,
        private readonly OrderProviderInterface $orderProvider,
        private readonly CapturePaymentResolverInterface $capturePaymentResolver,
        private readonly ?OrderPaymentsRemoverInterface $orderPaymentsRemover = null,
        private readonly ?OrderProcessorInterface $orderProcessor = null,
        private readonly ?PaymentStateManagerInterface $paymentStateManager = null,
    ) {
        if (null !== $this->payum) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                sprintf(
                    'Passing an instance of "%s" as the first argument is deprecated and will be prohibited in 2.0',
                    Payum::class,
                ),
            );
        }
        if (null !== $this->orderRepository) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                sprintf(
                    'Passing an instance of "%s" as the second argument is deprecated and will be prohibited in 2.0',
                    OrderRepositoryInterface::class,
                ),
            );
        }
        if (null !== $this->stateMachineFactory) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                sprintf(
                    'Passing an instance of "%s" as the third argument is deprecated and will be prohibited in 2.0',
                    FactoryInterface::class,
                ),
            );
        }
        if (null === $this->orderPaymentsRemover) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                'Not passing an $orderPaymentsRemover to %s constructor is deprecated and will be prohibited in 3.0',
                self::class,
            );
        }
        if (null === $this->orderProcessor) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                'Not passing an $orderProcessor to %s constructor is deprecated and will be prohibited in 3.0',
                self::class,
            );
        }
        if (null === $this->paymentStateManager) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.7',
                'Not passing an $paymentStateManager to %s constructor is deprecated and will be prohibited in 3.0',
                self::class,
            );
        }
    }

    public function __invoke(Request $request): Response
    {
        $id = $request->attributes->getInt('id');
        $order = $this->orderProvider->provideOrderById($id);

        try {
            $payment = $this->getPayment($order);
            $this->capturePaymentResolver->resolve($payment);
        } catch (\DomainException|GuzzleException) {
            /** @var FlashBagInterface $flashBag */
            $flashBag = $request->getSession()->getBag('flashes');
            $flashBag->add('error', 'sylius.pay_pal.something_went_wrong');

            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        $this->paymentManager->flush();

        return new JsonResponse([
            'id' => $order->getId(),
            'orderID' => $payment->getDetails()['paypal_order_id'],
            'status' => $payment->getState(),
        ]);
    }

    private function getPayment(OrderInterface $order): PaymentInterface
    {
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment();
        if ($payment === null) {
            throw new PaymentNotFoundException();
        }

        /** @var PaymentMethodInterface|null $paymentMethod */
        $paymentMethod = $payment->getMethod();
        $factoryName = $paymentMethod?->getGatewayConfig()?->getFactoryName();

        if ($payment->getState() === PaymentInterface::STATE_PROCESSING) {
            if ($this->paymentStateManager === null) {
                throw new \DomainException('PaymentStateManager must be provided to cancel old payment.');
            }

            $this->paymentStateManager->cancel($payment);
        }

        if ($payment->getState() === PaymentInterface::STATE_CART) {
            if ($factoryName === SyliusPayPalExtension::PAYPAL_FACTORY_NAME) {
                return $payment;
            }

            if ($this->orderPaymentsRemover === null || $this->orderProcessor === null) {
                throw new \DomainException('OrderPaymentsRemover and OrderProcessor must be provided to create a new payment.');
            }

            $this->orderPaymentsRemover->removePayments($order);
        }

        $this->orderProcessor->process($order);

        return $order->getLastPayment(PaymentInterface::STATE_CART);
    }
}
