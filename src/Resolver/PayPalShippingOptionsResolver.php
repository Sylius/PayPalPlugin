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

namespace Sylius\PayPalPlugin\Resolver;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Shipping\Calculator\DelegatingCalculatorInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;

final readonly class PayPalShippingOptionsResolver implements PayPalShippingOptionsResolverInterface
{
    private const TYPE_SHIPPING = 'SHIPPING';

    public function __construct(
        private ShippingMethodsResolverInterface $shippingMethodsResolver,
        private DelegatingCalculatorInterface $shippingCalculator,
    ) {
    }

    public function resolve(OrderInterface $order, AddressInterface $shippingAddress): array
    {
        $shipment = $order->getShipments()->first();
        if (!$shipment instanceof ShipmentInterface) {
            return [];
        }

        $originalShippingAddress = $order->getShippingAddress();
        $originalMethod = $shipment->getMethod();
        $order->setShippingAddress($shippingAddress);

        try {
            $options = [];

            foreach ($this->shippingMethodsResolver->getSupportedMethods($shipment) as $method) {
                $options[] = $this->buildOption($order, $shipment, $method, $originalMethod);
            }
        } finally {
            $shipment->setMethod($originalMethod);
            $order->setShippingAddress($originalShippingAddress);
        }

        return $this->withExactlyOneSelected($options);
    }

    /** @return array<string, mixed> */
    private function buildOption(
        OrderInterface $order,
        ShipmentInterface $shipment,
        ShippingMethodInterface $method,
        ?ShippingMethodInterface $currentMethod,
    ): array {
        $shipment->setMethod($method);

        return [
            'id' => (string) $method->getCode(),
            'amount' => [
                'currency_code' => (string) $order->getCurrencyCode(),
                'value' => number_format($this->shippingCalculator->calculate($shipment) / 100, 2, '.', ''),
            ],
            'type' => self::TYPE_SHIPPING,
            'label' => (string) $method->getName(),
            'selected' => $method === $currentMethod,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $options
     *
     * @return array<int, array<string, mixed>>
     */
    private function withExactlyOneSelected(array $options): array
    {
        if ([] === $options || in_array(true, array_column($options, 'selected'), true)) {
            return $options;
        }

        $cheapest = array_key_first($options);
        foreach ($options as $index => $option) {
            if ((float) $option['amount']['value'] < (float) $options[$cheapest]['amount']['value']) {
                $cheapest = $index;
            }
        }

        $options[$cheapest]['selected'] = true;

        return $options;
    }
}
