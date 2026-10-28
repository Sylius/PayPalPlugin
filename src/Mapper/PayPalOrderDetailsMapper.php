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

namespace Sylius\PayPalPlugin\Mapper;

use Sylius\PayPalPlugin\AmountUtils;
use Sylius\PayPalPlugin\Model\Payer;
use Sylius\PayPalPlugin\Model\PayPalAddress;
use Sylius\PayPalPlugin\Model\PayPalAuthorization;
use Sylius\PayPalPlugin\Model\PayPalCapture;
use Sylius\PayPalPlugin\Model\PayPalOrder;
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;
use Sylius\PayPalPlugin\Model\PayPalRefund;
use Sylius\PayPalPlugin\Model\PayPalShipping;

final readonly class PayPalOrderDetailsMapper implements PayPalOrderDetailsMapperInterface
{
    public function map(array $payPalOrder): PayPalOrderDetails
    {
        $purchaseUnits = is_array($payPalOrder['purchase_units'] ?? null) ? $payPalOrder['purchase_units'] : [];
        $purchaseUnit = is_array($purchaseUnits[0] ?? null) ? $purchaseUnits[0] : [];
        $shipping = is_array($purchaseUnit['shipping'] ?? null) ? $purchaseUnit['shipping'] : [];
        $payer = is_array($payPalOrder['payer'] ?? null) ? $payPalOrder['payer'] : [];
        $refunds = $purchaseUnit['payments']['refunds'] ?? [];

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
            [] !== $shipping ? $this->mapShipping($shipping) : null,
            [] !== $payer ? $this->mapPayer($payer) : null,
            array_values(array_filter(array_map(
                static fn (mixed $refund): ?PayPalRefund => is_array($refund) ? PayPalRefund::fromRefund($refund) : null,
                is_array($refunds) ? $refunds : [],
            ))),
        );
    }

    public function mapAddress(array $payPalAddress): PayPalAddress
    {
        return new PayPalAddress(
            $this->stringOrNull($payPalAddress['address_line_1'] ?? null),
            $this->stringOrNull($payPalAddress['admin_area_1'] ?? null),
            $this->stringOrNull($payPalAddress['admin_area_2'] ?? null),
            $this->stringOrNull($payPalAddress['postal_code'] ?? null),
            $this->stringOrNull($payPalAddress['country_code'] ?? null),
        );
    }

    /** @param array<string, mixed> $shipping */
    private function mapShipping(array $shipping): PayPalShipping
    {
        return new PayPalShipping(
            $this->stringOrNull($shipping['name']['full_name'] ?? null),
            is_array($shipping['address'] ?? null) ? $this->mapAddress($shipping['address']) : null,
            $this->findSelectedShippingOptionId($shipping),
            $this->stringOrNull($shipping['phone_number']['national_number'] ?? null),
        );
    }

    /** @param array<string, mixed> $payer */
    private function mapPayer(array $payer): Payer
    {
        return new Payer(
            $this->stringOrNull($payer['email_address'] ?? null),
            $this->stringOrNull($payer['name']['given_name'] ?? null),
            $this->stringOrNull($payer['name']['surname'] ?? null),
            $this->stringOrNull($payer['address']['country_code'] ?? null),
            $this->stringOrNull($payer['phone']['phone_number']['national_number'] ?? null),
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
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
