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
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;
use Sylius\PayPalPlugin\Exception\ShippingMethodNotAvailableException;
use Sylius\PayPalPlugin\Factory\PayPalPurchaseUnitFactoryInterface;
use Sylius\PayPalPlugin\Model\PayPalShippingOption;

final readonly class PayPalShippingCallbackAmountProvider implements PayPalShippingCallbackAmountProviderInterface
{
    public function __construct(
        private OrderProcessorInterface $orderProcessor,
        private ShippingMethodsResolverInterface $shippingMethodsResolver,
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
        if (!$shipment instanceof ShipmentInterface) {
            throw ShippingMethodNotAvailableException::withCode($selectedOption->id());
        }

        $shipment->setMethod($this->getSupportedMethod($shipment, $selectedOption->id()));

        $this->orderProcessor->process($order);

        $purchaseUnit = $this->payPalPurchaseUnitFactory->create(
            $payment,
            (string) ($payment->getDetails()['reference_id'] ?? ''),
        );

        return (array) $purchaseUnit->toArray()['amount'];
    }

    private function getSupportedMethod(ShipmentInterface $shipment, string $code): ShippingMethodInterface
    {
        foreach ($this->shippingMethodsResolver->getSupportedMethods($shipment) as $method) {
            if ($code === $method->getCode()) {
                return $method;
            }
        }

        throw ShippingMethodNotAvailableException::withCode($code);
    }
}
