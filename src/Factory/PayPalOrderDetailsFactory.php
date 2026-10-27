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

use Sylius\PayPalPlugin\AmountUtils;
use Sylius\PayPalPlugin\Model\PayPalAuthorization;
use Sylius\PayPalPlugin\Model\PayPalCapture;
use Sylius\PayPalPlugin\Model\PayPalOrder;
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;

final readonly class PayPalOrderDetailsFactory implements PayPalOrderDetailsFactoryInterface
{
    public function create(array $payPalOrder): PayPalOrderDetails
    {
        $purchaseUnits = is_array($payPalOrder['purchase_units'] ?? null) ? $payPalOrder['purchase_units'] : [];
        $purchaseUnit = is_array($purchaseUnits[0] ?? null) ? $purchaseUnits[0] : [];
        $shipping = is_array($purchaseUnit['shipping'] ?? null) ? $purchaseUnit['shipping'] : [];
        $payer = is_array($payPalOrder['payer'] ?? null) ? $payPalOrder['payer'] : [];

        return new PayPalOrderDetails(
            (string) ($payPalOrder['id'] ?? ''),
            $this->stringOrNull($payPalOrder['status'] ?? null),
            $this->stringOrNull($payPalOrder['intent'] ?? null) ?? PayPalOrder::INTENT_CAPTURE,
            $this->stringOrNull($purchaseUnit['reference_id'] ?? null),
            array_sum(array_map(
                fn (mixed $unit): int => is_array($unit) ? $this->amountOf($unit) : 0,
                $purchaseUnits,
            )),
            [] !== ($purchaseUnit['items'] ?? []),
            PayPalCapture::fromPayPalOrder($payPalOrder),
            PayPalAuthorization::fromPayPalOrder($payPalOrder),
            is_array($payPalOrder['payment_source'] ?? null) ? $payPalOrder['payment_source'] : [],
            $this->stringOrNull($shipping['name']['full_name'] ?? null),
            is_array($shipping['address'] ?? null) ? $shipping['address'] : null,
            $this->findSelectedShippingOptionId($shipping),
            $this->stringOrNull($payer['email_address'] ?? null),
            $this->stringOrNull($payer['name']['given_name'] ?? null),
            $this->stringOrNull($payer['name']['surname'] ?? null),
            $this->stringOrNull($payer['address']['country_code'] ?? null),
            $this->stringOrNull($shipping['phone_number']['national_number'] ?? null)
                ?? $this->stringOrNull($payer['phone']['phone_number']['national_number'] ?? null),
        );
    }

    /** @param array<string, mixed> $purchaseUnit */
    private function amountOf(array $purchaseUnit): int
    {
        $value = $purchaseUnit['amount']['value'] ?? null;

        return is_string($value) || is_numeric($value) ? AmountUtils::toMinorUnits((string) $value) : 0;
    }

    /** @param array<string, mixed> $shipping */
    private function findSelectedShippingOptionId(array $shipping): ?string
    {
        foreach ((array) ($shipping['options'] ?? []) as $option) {
            if (is_array($option) && true === ($option['selected'] ?? false)) {
                return $this->stringOrNull($option['id'] ?? null);
            }
        }

        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && '' !== $value ? $value : null;
    }
}
