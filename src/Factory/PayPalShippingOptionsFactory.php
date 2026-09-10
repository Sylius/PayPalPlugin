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

        if ([] === $options || $this->hasSelected($options)) {
            return new PayPalShippingOptions(...$options);
        }

        $options[0] = $options[0]->withSelected(true);

        return new PayPalShippingOptions(...$options);
    }

    /** @param array<int, PayPalShippingOption> $options */
    private function hasSelected(array $options): bool
    {
        foreach ($options as $option) {
            if ($option->isSelected()) {
                return true;
            }
        }

        return false;
    }
}
