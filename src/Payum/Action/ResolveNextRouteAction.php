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

        if ($payment->getState() === PaymentInterface::STATE_NEW) {
            $request->setRouteName('sylius_paypal_plugin_pay_with_paypal_form');
            $request->setRouteParameters(['id' => $payment->getId()]);

            return;
        }

        if ($payment->getState() === PaymentInterface::STATE_COMPLETED) {
            $request->setRouteName('sylius_shop_order_thank_you');

            return;
        }

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $request->setRouteName('sylius_shop_order_show');
        $request->setRouteParameters(['tokenValue' => $order->getTokenValue()]);
    }

    public function supports($request)
    {
        return $request instanceof ResolveNextRoute && $request->getFirstModel() instanceof PaymentInterface;
    }
}
