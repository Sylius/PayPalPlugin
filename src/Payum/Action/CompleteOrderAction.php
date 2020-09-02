<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Payum\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\StateResolver\StateResolverInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderApiInterface;
use Sylius\PayPalPlugin\Payum\Request\CompleteOrder;
use Sylius\PayPalPlugin\Processor\PayPalAddressProcessor;
use Sylius\PayPalPlugin\Updater\PaymentUpdaterInterface;

final class CompleteOrderAction implements ActionInterface
{
    /** @var AuthorizeClientApiInterface */
    private $authorizeClientApi;

    /** @var UpdateOrderApiInterface */
    private $updateOrderApi;

    /** @var CompleteOrderApiInterface */
    private $completeOrderApi;

    /** @var PayPalAddressProcessor */
    private $payPalAddressProcessor;

    /** @var PaymentUpdaterInterface */
    private $payPalPaymentUpdater;

    /** @var StateResolverInterface */
    private $orderPaymentStateResolver;

    public function __construct(
        AuthorizeClientApiInterface $authorizeClientApi,
        UpdateOrderApiInterface $updateOrderApi,
        CompleteOrderApiInterface $completeOrderApi,
        PayPalAddressProcessor $payPalAddressProcessor,
        PaymentUpdaterInterface $payPalPaymentUpdater,
        StateResolverInterface $orderPaymentStateResolver
    ) {
        $this->authorizeClientApi = $authorizeClientApi;
        $this->updateOrderApi = $updateOrderApi;
        $this->completeOrderApi = $completeOrderApi;
        $this->payPalAddressProcessor = $payPalAddressProcessor;
        $this->payPalPaymentUpdater = $payPalPaymentUpdater;
        $this->orderPaymentStateResolver = $orderPaymentStateResolver;
    }

    /** @param CompleteOrder $request */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getModel();
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = $gatewayConfig->getConfig();

        $token = $this
            ->authorizeClientApi
            ->authorize((string) $config['client_id'], (string) $config['client_secret'])
        ;

        $details = $payment->getDetails();
        /** @var OrderInterface $order */
        $order = $payment->getOrder();
        /** @var string $currencyCode */
        $currencyCode = $order->getCurrencyCode();

        if ($payment->getAmount() !== $order->getTotal()) {
            $this->updateOrderApi->update(
                $token,
                (string) $details['paypal_order_id'],
                (string) ($order->getTotal() / 100),
                $currencyCode
            );

            $this->payPalPaymentUpdater->updateAmount($payment, $order->getTotal());
            $this->orderPaymentStateResolver->resolve($order);
        }

        $content = $this->completeOrderApi->complete($token, $request->getOrderId());

        if ($content['status'] === 'COMPLETED') {
            $payment->setDetails([
                'status' => StatusAction::STATUS_COMPLETED,
                'paypal_order_id' => $content['id'],
                'paypal_payment_id' => $content['purchase_units'][0]['payments']['captures'][0]['id'],
            ]);

            $this->payPalAddressProcessor->process($content['purchase_units'][0]['shipping']['address'], $order);
        }
    }

    public function supports($request): bool
    {
        return
            $request instanceof CompleteOrder &&
            $request->getModel() instanceof PaymentInterface
        ;
    }
}
