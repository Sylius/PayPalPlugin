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

final readonly class PayPalShippingCallbackResponseFactory implements PayPalShippingCallbackResponseFactoryInterface
{
    private const ADDED_BREAKDOWN_KEYS = ['item_total', 'tax_total', 'shipping', 'handling', 'insurance'];

    private const SUBTRACTED_BREAKDOWN_KEYS = ['discount', 'shipping_discount'];

    public function create(string $payPalOrderId, array $purchaseUnit, array $shippingOptions): array
    {
        return [
            'id' => $payPalOrderId,
            'purchase_units' => [$this->createPurchaseUnit($purchaseUnit, $shippingOptions)],
        ];
    }

    /**
     * @param array<string, mixed> $purchaseUnit
     * @param array<int, array<string, mixed>> $shippingOptions
     *
     * @return array<string, mixed>
     */
    private function createPurchaseUnit(array $purchaseUnit, array $shippingOptions): array
    {
        $responseUnit = [];

        if (isset($purchaseUnit['reference_id'])) {
            $responseUnit['reference_id'] = $purchaseUnit['reference_id'];
        }

        $responseUnit['amount'] = $this->withSelectedShippingCost(
            (array) ($purchaseUnit['amount'] ?? []),
            $shippingOptions,
        );
        $responseUnit['shipping_options'] = $shippingOptions;

        return $responseUnit;
    }

    /**
     * @param array<string, mixed> $amount
     * @param array<int, array<string, mixed>> $shippingOptions
     *
     * @return array<string, mixed>
     */
    private function withSelectedShippingCost(array $amount, array $shippingOptions): array
    {
        $selected = $this->getSelectedOption($shippingOptions);

        /** @var array<string, array<string, mixed>> $breakdown */
        $breakdown = (array) ($amount['breakdown'] ?? []);
        if (null === $selected || [] === $breakdown) {
            return $amount;
        }

        $breakdown['shipping'] = (array) $selected['amount'];

        $total = 0;
        foreach (self::ADDED_BREAKDOWN_KEYS as $key) {
            $total += $this->minorUnits($breakdown, $key);
        }
        foreach (self::SUBTRACTED_BREAKDOWN_KEYS as $key) {
            $total -= $this->minorUnits($breakdown, $key);
        }

        $amount['breakdown'] = $breakdown;
        $amount['value'] = number_format($total / 100, 2, '.', '');

        return $amount;
    }

    /**
     * @param array<int, array<string, mixed>> $shippingOptions
     *
     * @return array<string, mixed>|null
     */
    private function getSelectedOption(array $shippingOptions): ?array
    {
        foreach ($shippingOptions as $option) {
            if (true === ($option['selected'] ?? false)) {
                return $option;
            }
        }

        return null;
    }

    /** @param array<string, array<string, mixed>> $breakdown */
    private function minorUnits(array $breakdown, string $key): int
    {
        return (int) round(((float) ($breakdown[$key]['value'] ?? 0)) * 100);
    }
}
