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

namespace Sylius\PayPalPlugin\Factory;

use Sylius\PayPalPlugin\Model\PayPalShippingOption;
use Sylius\PayPalPlugin\Model\PayPalShippingOptions;

final readonly class PayPalShippingOptionsFactory implements PayPalShippingOptionsFactoryInterface
{
    public function create(array $options): PayPalShippingOptions
    {
        $options = array_values($options);

        if ([] === $options || null !== $this->getSelectedIndex($options)) {
            return new PayPalShippingOptions(...$options);
        }

        $default = $this->getDefaultIndex($options);
        $options[$default] = $options[$default]->withSelected(true);

        return new PayPalShippingOptions(...$options);
    }

    /** @param array<int, PayPalShippingOption> $options */
    private function getSelectedIndex(array $options): ?int
    {
        foreach ($options as $index => $option) {
            if ($option->isSelected()) {
                return $index;
            }
        }

        return null;
    }

    /** @param array<int, PayPalShippingOption> $options */
    private function getDefaultIndex(array $options): int
    {
        $cheapest = 0;

        foreach ($options as $index => $option) {
            if ($option->amount() < $options[$cheapest]->amount()) {
                $cheapest = $index;
            }
        }

        return $cheapest;
    }
}
