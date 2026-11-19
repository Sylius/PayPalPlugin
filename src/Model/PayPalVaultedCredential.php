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

use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Webmozart\Assert\Assert;

final readonly class PayPalVaultedCredential
{
    public const VAULT_ID = 'vault_id';

    public const PAYMENT_SOURCE = 'payment_source';

    public const PREVIOUS_TRANSACTION_REFERENCE = 'previous_transaction_reference';

    public const PAYMENT_SOURCES = [
        PayPalPaymentSourceProviderInterface::PAYPAL,
        PayPalPaymentSourceProviderInterface::CARD,
    ];

    public function __construct(
        private string $vaultId,
        private string $paymentSource = PayPalPaymentSourceProviderInterface::PAYPAL,
        private ?string $previousTransactionReference = null,
    ) {
        Assert::notEmpty($vaultId);
        Assert::oneOf($paymentSource, self::PAYMENT_SOURCES);
        Assert::nullOrNotEmpty($previousTransactionReference);
    }

    public static function fromPaymentDetails(PayPalPaymentDetails $details): ?self
    {
        return self::fromArray([
            self::VAULT_ID => $details->vaultId(),
            self::PAYMENT_SOURCE => $details->paymentSource(),
            self::PREVIOUS_TRANSACTION_REFERENCE => $details->transactionId() ?? $details->authorizationId(),
        ]);
    }

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): ?self
    {
        $vaultId = $data[self::VAULT_ID] ?? null;
        $paymentSource = $data[self::PAYMENT_SOURCE] ?? PayPalPaymentSourceProviderInterface::PAYPAL;
        $previousTransactionReference = $data[self::PREVIOUS_TRANSACTION_REFERENCE] ?? null;

        if (
            !is_string($vaultId) ||
            '' === $vaultId ||
            !in_array($paymentSource, self::PAYMENT_SOURCES, true)
        ) {
            return null;
        }

        return new self(
            $vaultId,
            $paymentSource,
            is_string($previousTransactionReference) && '' !== $previousTransactionReference ? $previousTransactionReference : null,
        );
    }

    public function vaultId(): string
    {
        return $this->vaultId;
    }

    public function paymentSource(): string
    {
        return $this->paymentSource;
    }

    public function previousTransactionReference(): ?string
    {
        return $this->previousTransactionReference;
    }

    public function isCard(): bool
    {
        return PayPalPaymentSourceProviderInterface::CARD === $this->paymentSource;
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            self::VAULT_ID => $this->vaultId,
            self::PAYMENT_SOURCE => $this->paymentSource,
            self::PREVIOUS_TRANSACTION_REFERENCE => $this->previousTransactionReference,
        ], static fn (?string $value): bool => null !== $value);
    }
}
