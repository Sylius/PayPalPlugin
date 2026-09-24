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

namespace Sylius\PayPalPlugin\Payum\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Model\GatewayConfigInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\StateResolver\StateResolverInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderAddressApiInterface;
use Sylius\PayPalPlugin\Api\UpdateOrderApiInterface;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;
use Sylius\PayPalPlugin\Payum\Request\CompleteOrder;
use Sylius\PayPalPlugin\Processor\PayPalAddressProcessorInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Sylius\PayPalPlugin\Updater\PaymentUpdaterInterface;

final readonly class CompleteOrderAction implements ActionInterface
{
    public function __construct(
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private UpdateOrderApiInterface $updateOrderApi,
        private CompleteOrderApiInterface $completeOrderApi,
        private OrderDetailsApiInterface $orderDetailsApi,
        private ?PayPalAddressProcessorInterface $payPalAddressProcessor,
        private PaymentUpdaterInterface $payPalPaymentUpdater,
        private StateResolverInterface $orderPaymentStateResolver,
        private ?UpdateOrderAddressApiInterface $updateOrderAddressApi = null,
        private ?LoggerInterface $logger = null,
    ) {
        if (null !== $this->payPalAddressProcessor) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.7',
                sprintf(
                    'Passing an instance of "%s" as the fifth argument is deprecated and will be prohibited in 3.0',
                    PayPalAddressProcessorInterface::class,
                ),
            );
        }
        if (null === $this->updateOrderAddressApi) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.7',
                sprintf(
                    'Not passing $updateOrderAddressApi to "%s" constructor is deprecated and will be prohibited in 3.0',
                    self::class,
                ),
            );
        }
    }

    /** @param CompleteOrder $request */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getModel();
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        $details = $payment->getDetails();
        $paymentSource = is_string($details['payment_source'] ?? null)
            ? $details['payment_source']
            : PayPalPaymentSourceProviderInterface::PAYPAL;

        if (null !== RedirectPaymentSource::tryFrom($paymentSource)) {
            $this->logger?->warning(sprintf(
                'A "%s" PayPal order is captured by PayPal on payment approval and must not be completed here.',
                $paymentSource,
            ));

            return;
        }

        $token = $this->authorizeClientApi->authorize($paymentMethod);

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
                $config['merchant_id'],
            );

            $this->payPalPaymentUpdater->updateAmount($payment, $order->getTotal());
            $this->orderPaymentStateResolver->resolve($order);
        }

        if (null !== $this->updateOrderAddressApi && $order->isShippingRequired()) {
            $this->updateOrderAddressApi->update(
                $token,
                (string) $details['paypal_order_id'],
                (string) $details['reference_id'],
                $order->getShippingAddress(),
            );
        }
        $this->completeOrderApi->complete($token, $request->getOrderId());
        $orderDetails = $this->orderDetailsApi->get($token, $request->getOrderId());

        $details = [
            'status' => $orderDetails['status'] === 'COMPLETED' ? StatusAction::STATUS_COMPLETED : StatusAction::STATUS_PROCESSING,
            'paypal_order_id' => $orderDetails['id'],
            'reference_id' => $orderDetails['purchase_units'][0]['reference_id'],
            'payment_source' => $paymentSource,
        ];
        if (isset($orderDetails['purchase_units'][0]['payments']['captures'][0]['id'])) {
            $details = array_merge(
                $details,
                ['transaction_id' => $orderDetails['purchase_units'][0]['payments']['captures'][0]['id']],
            );
        }

        $payment->setDetails($details);
    }

    public function supports($request): bool
    {
        return
            $request instanceof CompleteOrder &&
            $request->getModel() instanceof PaymentInterface
        ;
    }
}
