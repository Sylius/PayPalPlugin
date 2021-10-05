<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Payum\Action;

use Payum\Core\Action\ActionInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Bundle\PayumBundle\Request\ResolveNextRoute;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class ResolveNextRouteAction implements ActionInterface
{
    /** @param ResolveNextRoute $request */
    public function execute($request): void
    {
        /** @var PaymentInterface $payment */
        $payment = $request->getFirstModel();

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        if ($payment->getState() === PaymentInterface::STATE_NEW) {
            $request->setRouteName('sylius_paypal_plugin_pay_with_paypal_form');
            $request->setRouteParameters(
                ['orderToken' => $order->getTokenValue(), 'paymentId' => $payment->getId()]
            );

            return;
        }

        if ($payment->getState() === PaymentInterface::STATE_COMPLETED) {
            $request->setRouteName('sylius_shop_order_thank_you');

            return;
        }

        $request->setRouteName('sylius_shop_order_show');
        $request->setRouteParameters(['tokenValue' => $order->getTokenValue()]);
    }

    public function supports($request)
    {
        if (
            !$request instanceof ResolveNextRoute ||
            !$request->getFirstModel() instanceof PaymentInterface
        ) {
            return false;
        }

        /** @var PaymentInterface $model */
        $model = $request->getFirstModel();
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $model->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return $gatewayConfig->getFactoryName() === 'sylius.pay_pal';
    }
}
