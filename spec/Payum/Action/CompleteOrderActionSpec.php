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

namespace spec\Sylius\PayPalPlugin\Payum\Action;

use Payum\Core\Action\ActionInterface;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\StateResolver\StateResolverInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderApiInterface;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Payum\Request\CompleteOrder;
use Sylius\PayPalPlugin\Processor\PayPalAddressProcessorInterface;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProviderInterface;
use Sylius\PayPalPlugin\Updater\PaymentUpdaterInterface;

final class CompleteOrderActionSpec extends ObjectBehavior
{
    function let(
        CacheAuthorizeClientApiInterface $authorizeClientApi,
        UpdateOrderApiInterface $updateOrderApi,
        CompleteOrderApiInterface $completeOrderApi,
        OrderDetailsApiInterface $orderDetailsApi,
        PayPalAddressProcessorInterface $payPalAddressProcessor,
        PaymentUpdaterInterface $payPalPaymentUpdater,
        StateResolverInterface $orderPaymentStateResolver,
        PayPalItemDataProviderInterface $payPalItemsDataProvider
    ): void {
        $this->beConstructedWith(
            $authorizeClientApi,
            $updateOrderApi,
            $completeOrderApi,
            $orderDetailsApi,
            $payPalAddressProcessor,
            $payPalPaymentUpdater,
            $orderPaymentStateResolver,
            $payPalItemsDataProvider
        );
    }

    function it_implements_action_interface(): void
    {
        $this->shouldImplement(ActionInterface::class);
    }

    function it_completes_order(
        CacheAuthorizeClientApiInterface $authorizeClientApi,
        CompleteOrderApiInterface $completeOrderApi,
        OrderDetailsApiInterface $orderDetailsApi,
        CompleteOrder $request,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        OrderInterface $order
    ): void {
        $request->getModel()->willReturn($payment);
        $payment->getMethod()->willReturn($paymentMethod);
        $payment->getDetails()->willReturn([]);
        $payment->getOrder()->willReturn($order);

        $authorizeClientApi->authorize($paymentMethod)->willReturn('TOKEN');

        $request->getOrderId()->willReturn('123123');

        $payment->getAmount()->willReturn(1000);;
        $order->getTotal()->willReturn(1000);

        $completeOrderApi->complete('TOKEN', '123123')->shouldBeCalled();
        $orderDetailsApi->get('TOKEN', '123123')->willReturn([
            'status' => 'COMPLETED',
            'id' => '123123',
            'purchase_units' => [
                ['reference_id' => 'REFERENCE_ID']
            ]
        ]);

        $payment->setDetails([
            'status' => StatusAction::STATUS_COMPLETED,
            'paypal_order_id' => '123123',
            'reference_id' => 'REFERENCE_ID',
        ])->shouldBeCalled();

        $order->isShippingRequired()->willReturn(false);

        $this->execute($request);
    }

    function it_completes_order_and_saves_transaction_id(
        CacheAuthorizeClientApiInterface $authorizeClientApi,
        CompleteOrderApiInterface $completeOrderApi,
        OrderDetailsApiInterface $orderDetailsApi,
        CompleteOrder $request,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        OrderInterface $order
    ): void {
        $request->getModel()->willReturn($payment);
        $payment->getMethod()->willReturn($paymentMethod);
        $payment->getDetails()->willReturn([]);
        $payment->getOrder()->willReturn($order);

        $authorizeClientApi->authorize($paymentMethod)->willReturn('TOKEN');

        $request->getOrderId()->willReturn('123123');

        $payment->getAmount()->willReturn(1000);;
        $order->getTotal()->willReturn(1000);

        $completeOrderApi->complete('TOKEN', '123123')->shouldBeCalled();
        $orderDetailsApi->get('TOKEN', '123123')->willReturn([
            'status' => 'COMPLETED',
            'id' => '123123',
            'purchase_units' => [
                [
                    'reference_id' => 'REFERENCE_ID',
                    'payments' => ['captures' => [['id' => 'TRANSACTION_ID']]],
                ]
            ]
        ]);

        $payment->setDetails([
            'status' => StatusAction::STATUS_COMPLETED,
            'paypal_order_id' => '123123',
            'reference_id' => 'REFERENCE_ID',
            'transaction_id' => 'TRANSACTION_ID',
        ])->shouldBeCalled();

        $order->isShippingRequired()->willReturn(false);

        $this->execute($request);
    }
}
