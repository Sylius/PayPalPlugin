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
use Payum\Core\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\StateResolver\StateResolverInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderApiInterface;
use Sylius\PayPalPlugin\Payum\Request\CompleteOrder;
use Sylius\PayPalPlugin\Processor\PayPalAddressProcessor;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProviderInterface;
use Sylius\PayPalPlugin\Updater\PaymentUpdaterInterface;

final class CompleteOrderAction implements ActionInterface
{
    private CacheAuthorizeClientApiInterface $authorizeClientApi;

    private UpdateOrderApiInterface $updateOrderApi;

    private CompleteOrderApiInterface $completeOrderApi;

    private OrderDetailsApiInterface $orderDetailsApi;

    private PayPalAddressProcessor $payPalAddressProcessor;

    private PaymentUpdaterInterface $payPalPaymentUpdater;

    private StateResolverInterface $orderPaymentStateResolver;

    private PayPalItemDataProviderInterface $payPalItemsDataProvider;

    public function __construct(
        CacheAuthorizeClientApiInterface $authorizeClientApi,
        UpdateOrderApiInterface $updateOrderApi,
        CompleteOrderApiInterface $completeOrderApi,
        OrderDetailsApiInterface $orderDetailsApi,
        PayPalAddressProcessor $payPalAddressProcessor,
        PaymentUpdaterInterface $payPalPaymentUpdater,
        StateResolverInterface $orderPaymentStateResolver,
        PayPalItemDataProviderInterface $payPalItemsDataProvider
    ) {
        $this->authorizeClientApi = $authorizeClientApi;
        $this->updateOrderApi = $updateOrderApi;
        $this->completeOrderApi = $completeOrderApi;
        $this->orderDetailsApi = $orderDetailsApi;
        $this->payPalAddressProcessor = $payPalAddressProcessor;
        $this->payPalPaymentUpdater = $payPalPaymentUpdater;
        $this->orderPaymentStateResolver = $orderPaymentStateResolver;
        $this->payPalItemsDataProvider = $payPalItemsDataProvider;
    }

    /** @param CompleteOrder $request */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getModel();
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        $token = $this->authorizeClientApi->authorize($paymentMethod);

        $details = $payment->getDetails();
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        if ($payment->getAmount() !== $order->getTotal()) {
            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $paymentMethod->getGatewayConfig();
            $config = $gatewayConfig->getConfig();

            $this->updateOrderApi->update(
                $token,
                (string) $details['paypal_order_id'],
                $payment,
                (string) $details['reference_id'],
                $config['merchant_id']
            );

            $this->payPalPaymentUpdater->updateAmount($payment, $order->getTotal());
            $this->orderPaymentStateResolver->resolve($order);
        }

        $this->completeOrderApi->complete($token, $request->getOrderId());
        $orderDetails = $this->orderDetailsApi->get($token, $request->getOrderId());

        $payment->setDetails([
            'status' => $orderDetails['status'] === 'COMPLETED' ? StatusAction::STATUS_COMPLETED : StatusAction::STATUS_PROCESSING,
            'paypal_order_id' => $orderDetails['id'],
            'reference_id' => $orderDetails['purchase_units'][0]['reference_id'],
            'TRANSACTIONID' => $orderDetails['purchase_units'][0]["payments"]["captures"][0]["id"],
        ]);

        if ($order->isShippingRequired()) {
            $this->payPalAddressProcessor->process($orderDetails['purchase_units'][0]['shipping']['address'], $order);
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
