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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Payment\Remover\OrderPaymentsRemoverInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolverInterface;
use Sylius\PayPalPlugin\Resolver\PayPalPaymentMethodsResolverInterface;
use Sylius\PayPalPlugin\Verifier\OrderOwnershipVerifierInterface;
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
        private OrderPaymentsRemoverInterface $orderPaymentsRemover,
        private OrderProcessorInterface $orderProcessor,
        private PayPalPaymentMethodsResolverInterface $payPalMethodsResolver,
        private OrderOwnershipVerifierInterface $orderOwnershipVerifier,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $id = $request->attributes->getInt('id');
        $order = $this->orderProvider->provideOrderById($id);
        $this->orderOwnershipVerifier->verify($order, $request);

        try {
            $paymentMethod = $this->channelPayPalMethod($order, $request->query->getString('paymentMethod'));
        } catch (\InvalidArgumentException) {
            /** @var FlashBagInterface $flashBag */
            $flashBag = $request->getSession()->getBag('flashes');
            $flashBag->add('error', 'sylius_paypal.something_went_wrong');

            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        $paymentSource = $this->resolvePaymentSource($request, $paymentMethod);
        if (null === $paymentSource || !$this->isExpressCheckoutEnabled($paymentMethod)) {
            /** @var FlashBagInterface $flashBag */
            $flashBag = $request->getSession()->getBag('flashes');
            $flashBag->add('error', 'sylius_paypal.payment_source_not_available');

            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        try {
            $payment = $this->getPayment($order, $paymentMethod);
            $payment->setDetails(PayPalPaymentDetails::fromPayment($payment)->withPaymentSource($paymentSource)->toArray());
            $this->capturePaymentResolver->resolve($payment);
        } catch (\DomainException|PayPalApiException) {
            /** @var FlashBagInterface $flashBag */
            $flashBag = $request->getSession()->getBag('flashes');
            $flashBag->add('error', 'sylius_paypal.something_went_wrong');

            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        $this->paymentManager->flush();

        $payPalOrderId = PayPalPaymentDetails::fromPayment($payment)->payPalOrderId();

        return new JsonResponse([
            'id' => $order->getId(),
            'orderId' => $payPalOrderId,
            'status' => $payment->getState(),
        ]);
    }

    private function getPayment(OrderInterface $order, ?PaymentMethodInterface $channelPayPalMethod): PaymentInterface
    {
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);
        /** @var PaymentMethodInterface|null $paymentMethod */
        $paymentMethod = $payment->getMethod();
        $factoryName = $paymentMethod?->getGatewayConfig()?->getFactoryName();

        if ($factoryName === SyliusPayPalExtension::PAYPAL_FACTORY_NAME) {
            if (null !== $channelPayPalMethod && $channelPayPalMethod !== $paymentMethod) {
                $payment->setMethod($channelPayPalMethod);
            }

            return $payment;
        }

        $this->orderPaymentsRemover->removePayments($order);
        $this->orderProcessor->process($order);

        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);
        if (null !== $channelPayPalMethod) {
            $payment->setMethod($channelPayPalMethod);
        }

        return $payment;
    }

    private function channelPayPalMethod(OrderInterface $order, string $code): ?PaymentMethodInterface
    {
        $channel = $order->getChannel();
        if (null === $channel) {
            return null;
        }

        $paymentMethods = $this->payPalMethodsResolver->getInChannel($channel);
        if ('' === $code) {
            return $paymentMethods[0] ?? null;
        }

        foreach ($paymentMethods as $paymentMethod) {
            if ($paymentMethod->getCode() === $code) {
                return $paymentMethod;
            }
        }

        throw new \InvalidArgumentException(sprintf('There is no PayPal payment method "%s" in the channel of the order.', $code));
    }

    private function resolvePaymentSource(Request $request, ?PaymentMethodInterface $paymentMethod): ?string
    {
        $paymentSource = $request->query->get('paymentSource', PayPalPaymentSourceProviderInterface::PAYPAL);

        if (PayPalPaymentSourceProviderInterface::PAYPAL === $paymentSource) {
            return $paymentSource;
        }

        if (PayPalPaymentSourceProviderInterface::VENMO === $paymentSource && $this->isVenmoEnabled($paymentMethod)) {
            return $paymentSource;
        }

        return null;
    }

    private function isVenmoEnabled(?PaymentMethodInterface $paymentMethod): bool
    {
        $gatewayConfig = $paymentMethod?->getGatewayConfig();
        if (null === $gatewayConfig) {
            return false;
        }

        return PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->isVenmoEnabled();
    }

    private function isExpressCheckoutEnabled(?PaymentMethodInterface $paymentMethod): bool
    {
        $gatewayConfig = $paymentMethod?->getGatewayConfig();
        if (null === $gatewayConfig) {
            return true;
        }

        return PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->isExpressCheckoutEnabled();
    }
}
