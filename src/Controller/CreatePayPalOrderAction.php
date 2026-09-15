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

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class CreatePayPalOrderAction
{
    public function __construct(
        private PaymentStateManagerInterface $paymentStateManager,
        private OrderProviderInterface $orderProvider,
        private CapturePaymentResolverInterface $capturePaymentResolver,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $token = (string) $request->attributes->get('token');
        $order = $this->orderProvider->provideOrderByToken($token);

        $this->cancelLiveAttempt($order);

        $payment = $order->getLastPayment(PaymentInterface::STATE_NEW);
        if (null === $payment) {
            return new JsonResponse([], Response::HTTP_CONFLICT);
        }

        $this->capturePaymentResolver->resolve($payment);

        $this->paymentStateManager->process($payment);

        $payPalOrderId = $payment->getDetails()['paypal_order_id'];

        return new JsonResponse([
            'orderId' => $payPalOrderId,
            'orderID' => $payPalOrderId, // BC with 2.0. Deprecated in 2.1; use "orderId" instead.
            'status' => $payment->getState(),
        ]);
    }

    private function cancelLiveAttempt(OrderInterface $order): void
    {
        $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);

        if (null !== $payment && $this->isPayPalPayment($payment)) {
            $this->paymentStateManager->cancel($payment);
        }
    }

    private function isPayPalPayment(PaymentInterface $payment): bool
    {
        $paymentMethod = $payment->getMethod();
        if (!$paymentMethod instanceof PaymentMethodInterface) {
            return false;
        }

        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return
            $gatewayConfig instanceof GatewayConfigInterface &&
            $gatewayConfig->getFactoryName() === SyliusPayPalExtension::PAYPAL_FACTORY_NAME
        ;
    }
}
