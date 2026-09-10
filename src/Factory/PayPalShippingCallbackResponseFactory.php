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

use Sylius\PayPalPlugin\Model\PayPalShippingOptions;

final readonly class PayPalShippingCallbackResponseFactory implements PayPalShippingCallbackResponseFactoryInterface
{
    private const ADDED_BREAKDOWN_KEYS = ['item_total', 'tax_total', 'shipping', 'handling', 'insurance'];

    private const SUBTRACTED_BREAKDOWN_KEYS = ['discount', 'shipping_discount'];

    public function create(
        string $payPalOrderId,
        array $purchaseUnit,
        PayPalShippingOptions $shippingOptions,
    ): array {
        return [
            'id' => $payPalOrderId,
            'purchase_units' => [$this->createPurchaseUnit($purchaseUnit, $shippingOptions)],
        ];
    }

    /**
     * @param array<string, mixed> $purchaseUnit
     *
     * @return array<string, mixed>
     */
    private function createPurchaseUnit(array $purchaseUnit, PayPalShippingOptions $shippingOptions): array
    {
        $responseUnit = [];

        if (isset($purchaseUnit['reference_id'])) {
            $responseUnit['reference_id'] = $purchaseUnit['reference_id'];
        }

        $responseUnit['amount'] = $this->withSelectedShippingCost(
            (array) ($purchaseUnit['amount'] ?? []),
            $shippingOptions,
        );
        $responseUnit['shipping_options'] = $shippingOptions->toArray();

        return $responseUnit;
    }

    /**
     * @param array<string, mixed> $amount
     *
     * @return array<string, mixed>
     */
    private function withSelectedShippingCost(array $amount, PayPalShippingOptions $shippingOptions): array
    {
        $selected = $shippingOptions->selected();

        /** @var array<string, array<string, mixed>> $breakdown */
        $breakdown = (array) ($amount['breakdown'] ?? []);
        if (null === $selected || [] === $breakdown) {
            return $amount;
        }

        $breakdown['shipping'] = $selected->amountToArray();

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

    /** @param array<string, array<string, mixed>> $breakdown */
    private function minorUnits(array $breakdown, string $key): int
    {
        return (int) round(((float) ($breakdown[$key]['value'] ?? 0)) * 100);
    }
}
