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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Payment\Remover\OrderPaymentsRemoverInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolverInterface;
use Sylius\PayPalPlugin\Resolver\PayPalPaymentMethodsResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final readonly class CreatePayPalOrderFromCartAction
{
    public function __construct(
        private ObjectManager $paymentManager,
        private OrderProviderInterface $orderProvider,
        private CapturePaymentResolverInterface $capturePaymentResolver,
        private ?OrderPaymentsRemoverInterface $orderPaymentsRemover = null,
        private ?OrderProcessorInterface $orderProcessor = null,
        private ?PayPalPaymentMethodsResolverInterface $payPalMethodsResolver = null,
    ) {
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
        if (null === $this->payPalMethodsResolver) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.7',
                'Not passing a $payPalMethodsResolver to %s constructor is deprecated and will be prohibited in 3.0',
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
            $flashBag->add('error', 'sylius_paypal.something_went_wrong');

            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        $this->paymentManager->flush();

        return new JsonResponse([
            'id' => $order->getId(),
            'orderId' => $payment->getDetails()['paypal_order_id'],
            // BC with 2.0, where this key was spelled "orderID". Deprecated, removed in 3.0.
            'orderID' => $payment->getDetails()['paypal_order_id'],
            'status' => $payment->getState(),
        ]);
    }

    private function getPayment(OrderInterface $order): PaymentInterface
    {
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);
        /** @var PaymentMethodInterface|null $paymentMethod */
        $paymentMethod = $payment->getMethod();
        $factoryName = $paymentMethod?->getGatewayConfig()?->getFactoryName();

        if ($factoryName === SyliusPayPalExtension::PAYPAL_FACTORY_NAME) {
            return $payment;
        }

        if ($this->orderPaymentsRemover === null || $this->orderProcessor === null) {
            throw new \DomainException('OrderPaymentsRemover and OrderProcessor must be provided to create a new payment.');
        }

        $this->orderPaymentsRemover->removePayments($order);
        $this->orderProcessor->process($order);

        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);
        if ($order->getChannel() !== null && $this->payPalMethodsResolver !== null) {
            $paypalMethods = $this->payPalMethodsResolver->getInChannel($order->getChannel());
            if ([] !== $paypalMethods) {
                $payment->setMethod($paypalMethods[0]);
            }
        }

        return $payment;
    }
}
