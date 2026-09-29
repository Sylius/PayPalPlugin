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

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Sylius\PayPalPlugin\Factory\PayPalPurchaseUnitFactoryInterface;
use Sylius\PayPalPlugin\Model\PayPalShippingOption;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

final readonly class PayPalShippingCallbackAmountProvider implements PayPalShippingCallbackAmountProviderInterface
{
    /** @param RepositoryInterface<ShippingMethodInterface> $shippingMethodRepository */
    public function __construct(
        private OrderProcessorInterface $orderProcessor,
        private RepositoryInterface $shippingMethodRepository,
        private PayPalPurchaseUnitFactoryInterface $payPalPurchaseUnitFactory,
    ) {
    }

    public function provide(
        PaymentInterface $payment,
        AddressInterface $shippingAddress,
        PayPalShippingOption $selectedOption,
    ): array {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $order->setShippingAddress($shippingAddress);
        $order->setBillingAddress($shippingAddress);

        $shipment = $order->getShipments()->first();
        $selectedMethod = $this->shippingMethodRepository->findOneBy(['code' => $selectedOption->id()]);
        if ($shipment instanceof ShipmentInterface && $selectedMethod instanceof ShippingMethodInterface) {
            $shipment->setMethod($selectedMethod);
        }

        $this->orderProcessor->process($order);

        $purchaseUnit = $this->payPalPurchaseUnitFactory->create(
            $payment,
            (string) ($payment->getDetails()['reference_id'] ?? ''),
        );

        return (array) $purchaseUnit->toArray()['amount'];
    }
}
