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
use Sylius\PayPalPlugin\Model\PayPalShippingOption;

final readonly class PayPalShippingOptionsResolver implements PayPalShippingOptionsResolverInterface
{
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
                $options[] = $this->createOption($order, $shipment, $method, $originalMethod);
            }
        } finally {
            $shipment->setMethod($originalMethod);
            $order->setShippingAddress($originalShippingAddress);
        }

        return $this->withExactlyOneSelected($options);
    }

    private function createOption(
        OrderInterface $order,
        ShipmentInterface $shipment,
        ShippingMethodInterface $method,
        ?ShippingMethodInterface $currentMethod,
    ): PayPalShippingOption {
        $shipment->setMethod($method);

        return new PayPalShippingOption(
            (string) $method->getCode(),
            $this->getLabel($method, $order->getLocaleCode()),
            (string) $order->getCurrencyCode(),
            $this->shippingCalculator->calculate($shipment),
            $method === $currentMethod,
        );
    }

    private function getLabel(ShippingMethodInterface $method, ?string $localeCode): string
    {
        if (null !== $localeCode && '' !== $localeCode) {
            $name = $method->getTranslation($localeCode)->getName();

            if (null !== $name && '' !== $name) {
                return $name;
            }
        }

        return (string) $method->getName();
    }

    /**
     * @param array<int, PayPalShippingOption> $options
     *
     * @return array<int, PayPalShippingOption>
     */
    private function withExactlyOneSelected(array $options): array
    {
        if ([] === $options) {
            return $options;
        }

        foreach ($options as $option) {
            if ($option->isSelected()) {
                return $options;
            }
        }

        $cheapest = array_key_first($options);
        foreach ($options as $index => $option) {
            if ($option->amount() < $options[$cheapest]->amount()) {
                $cheapest = $index;
            }
        }

        $options[$cheapest] = $options[$cheapest]->withSelected(true);

        return $options;
    }
}
