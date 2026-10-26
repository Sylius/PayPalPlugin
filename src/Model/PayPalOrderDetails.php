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

namespace Sylius\PayPalPlugin\Model;

use Sylius\PayPalPlugin\AmountUtils;

final readonly class PayPalOrderDetails
{
    /**
     * @param array<string, mixed> $paymentSource
     * @param array<string, mixed>|null $shippingAddress
     */
    public function __construct(
        private string $id,
        private ?string $status = null,
        private string $intent = PayPalOrder::INTENT_CAPTURE,
        private ?string $referenceId = null,
        private ?int $amount = null,
        private int $totalAmount = 0,
        private bool $hasItems = false,
        private ?PayPalCapture $capture = null,
        private ?PayPalAuthorization $authorization = null,
        private array $paymentSource = [],
        private ?string $shippingFullName = null,
        private ?array $shippingAddress = null,
        private ?string $selectedShippingOptionId = null,
        private ?string $payerEmail = null,
        private ?string $payerGivenName = null,
        private ?string $payerSurname = null,
        private ?string $payerCountryCode = null,
        private ?string $phoneNumber = null,
    ) {
    }

    /** @param array<string, mixed> $payPalOrder */
    public static function fromArray(array $payPalOrder): self
    {
        $purchaseUnits = is_array($payPalOrder['purchase_units'] ?? null) ? $payPalOrder['purchase_units'] : [];
        $purchaseUnit = is_array($purchaseUnits[0] ?? null) ? $purchaseUnits[0] : [];
        $shipping = is_array($purchaseUnit['shipping'] ?? null) ? $purchaseUnit['shipping'] : [];
        $payer = is_array($payPalOrder['payer'] ?? null) ? $payPalOrder['payer'] : [];

        return new self(
            (string) ($payPalOrder['id'] ?? ''),
            self::stringOrNull($payPalOrder['status'] ?? null),
            self::stringOrNull($payPalOrder['intent'] ?? null) ?? PayPalOrder::INTENT_CAPTURE,
            self::stringOrNull($purchaseUnit['reference_id'] ?? null),
            self::amountOrNull($purchaseUnit),
            array_sum(array_map(
                static fn (mixed $unit): int => is_array($unit) ? (self::amountOrNull($unit) ?? 0) : 0,
                $purchaseUnits,
            )),
            [] !== ($purchaseUnit['items'] ?? []),
            PayPalCapture::fromPayPalOrder($payPalOrder),
            PayPalAuthorization::fromPayPalOrder($payPalOrder),
            is_array($payPalOrder['payment_source'] ?? null) ? $payPalOrder['payment_source'] : [],
            self::stringOrNull($shipping['name']['full_name'] ?? null),
            is_array($shipping['address'] ?? null) ? $shipping['address'] : null,
            self::findSelectedShippingOptionId($shipping),
            self::stringOrNull($payer['email_address'] ?? null),
            self::stringOrNull($payer['name']['given_name'] ?? null),
            self::stringOrNull($payer['name']['surname'] ?? null),
            self::stringOrNull($payer['address']['country_code'] ?? null),
            self::stringOrNull($shipping['phone_number']['national_number'] ?? null)
                ?? self::stringOrNull($payer['phone']['phone_number']['national_number'] ?? null),
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function status(): ?string
    {
        return $this->status;
    }

    public function isStatus(string $status): bool
    {
        return $status === $this->status;
    }

    public function intent(): string
    {
        return $this->intent;
    }

    public function referenceId(): ?string
    {
        return $this->referenceId;
    }

    public function amount(): ?int
    {
        return $this->amount;
    }

    public function totalAmount(): int
    {
        return $this->totalAmount;
    }

    public function hasItems(): bool
    {
        return $this->hasItems;
    }

    public function capture(): ?PayPalCapture
    {
        return $this->capture;
    }

    public function authorization(): ?PayPalAuthorization
    {
        return $this->authorization;
    }

    /** @return array<string, mixed> */
    public function paymentSource(): array
    {
        return $this->paymentSource;
    }

    public function shippingFullName(): ?string
    {
        return $this->shippingFullName;
    }

    /** @return array<string, mixed>|null */
    public function shippingAddress(): ?array
    {
        return $this->shippingAddress;
    }

    public function selectedShippingOptionId(): ?string
    {
        return $this->selectedShippingOptionId;
    }

    public function payerEmail(): ?string
    {
        return $this->payerEmail;
    }

    public function payerGivenName(): ?string
    {
        return $this->payerGivenName;
    }

    public function payerSurname(): ?string
    {
        return $this->payerSurname;
    }

    public function payerCountryCode(): ?string
    {
        return $this->payerCountryCode;
    }

    public function phoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    /** @param array<string, mixed> $purchaseUnit */
    private static function amountOrNull(array $purchaseUnit): ?int
    {
        $value = $purchaseUnit['amount']['value'] ?? null;

        return is_string($value) || is_numeric($value) ? AmountUtils::toMinorUnits((string) $value) : null;
    }

    /** @param array<string, mixed> $shipping */
    private static function findSelectedShippingOptionId(array $shipping): ?string
    {
        foreach ((array) ($shipping['options'] ?? []) as $option) {
            if (is_array($option) && true === ($option['selected'] ?? false)) {
                return self::stringOrNull($option['id'] ?? null);
            }
        }

        return null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && '' !== $value ? $value : null;
    }
}
