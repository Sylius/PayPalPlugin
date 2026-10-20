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

use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;

final readonly class PayPalPaymentDetails
{
    public const STATUS = 'status';

    public const PAYPAL_ORDER_ID = 'paypal_order_id';

    public const REFERENCE_ID = 'reference_id';

    public const AMOUNT = 'payment_amount';

    public const PAYMENT_SOURCE = 'payment_source';

    public const TRANSACTION_ID = 'transaction_id';

    public const PAYER_ACTION_URL = 'payer_action_url';

    private const LEGACY_PAYER_ACTION_RETURN_NONCE = 'payer_action_return_nonce';

    private const LEGACY_PAYER_ACTION_CANCEL_NONCE = 'payer_action_cancel_nonce';

    public const CAPTURED_AMOUNT = 'captured_amount';

    public const CAPTURED_CURRENCY_CODE = 'captured_currency_code';

    public const SUPERSEDED_PAYPAL_ORDERS = 'superseded_paypal_orders';

    public const REFUNDED = 'refunded';

    public const REFUND_ID = 'refund_id';

    public const VAULT_ID = 'vault_id';

    public const VAULT_STATUS = 'vault_status';

    public const VAULT_CUSTOMER_ID = 'vault_customer_id';

    public const PAYER_EMAIL = 'payer_email';

    /** @param array<string, mixed> $details */
    private function __construct(private array $details)
    {
    }

    public static function create(): self
    {
        return new self([]);
    }

    /** @param array<string, mixed> $details */
    public static function fromArray(array $details): self
    {
        return new self($details);
    }

    public static function fromPayment(PaymentInterface $payment): self
    {
        return new self($payment->getDetails());
    }

    public function status(): ?PayPalPaymentStatus
    {
        $status = $this->details[self::STATUS] ?? null;

        return is_string($status) ? PayPalPaymentStatus::tryFrom($status) : null;
    }

    public function isStatus(PayPalPaymentStatus $status): bool
    {
        return $status === $this->status();
    }

    public function payPalOrderId(): ?string
    {
        return $this->optional(self::PAYPAL_ORDER_ID);
    }

    public function hasPayPalOrderId(): bool
    {
        return null !== $this->payPalOrderId() && '' !== $this->payPalOrderId();
    }

    public function referenceId(): ?string
    {
        return $this->optional(self::REFERENCE_ID);
    }

    public function amount(): int
    {
        $amount = $this->details[self::AMOUNT] ?? null;

        return is_numeric($amount) ? (int) $amount : 0;
    }

    public function paymentSource(): string
    {
        $paymentSource = $this->details[self::PAYMENT_SOURCE] ?? null;

        return is_string($paymentSource) ? $paymentSource : PayPalPaymentSourceProviderInterface::PAYPAL;
    }

    public function transactionId(): ?string
    {
        return $this->optional(self::TRANSACTION_ID);
    }

    public function payerActionUrl(): ?string
    {
        return $this->optional(self::PAYER_ACTION_URL);
    }

    public function isRefunded(): bool
    {
        return true === ($this->details[self::REFUNDED] ?? false);
    }

    public function refundId(): ?string
    {
        return $this->optional(self::REFUND_ID);
    }

    public function vaultId(): ?string
    {
        return $this->optional(self::VAULT_ID);
    }

    public function vaultStatus(): ?string
    {
        return $this->optional(self::VAULT_STATUS);
    }

    public function vaultCustomerId(): ?string
    {
        return $this->optional(self::VAULT_CUSTOMER_ID);
    }

    public function payerEmail(): ?string
    {
        return $this->optional(self::PAYER_EMAIL);
    }

    /** @return array<string, string> */
    public function supersededPayPalOrders(): array
    {
        $supersededPayPalOrders = $this->details[self::SUPERSEDED_PAYPAL_ORDERS] ?? null;
        if (!is_array($supersededPayPalOrders)) {
            return [];
        }

        $paymentSources = [];
        foreach ($supersededPayPalOrders as $payPalOrderId => $paymentSource) {
            if (is_string($paymentSource)) {
                $paymentSources[(string) $payPalOrderId] = $paymentSource;
            }
        }

        return $paymentSources;
    }

    public function isSupersededPayPalOrder(string $payPalOrderId): bool
    {
        return array_key_exists($payPalOrderId, $this->supersededPayPalOrders());
    }

    public function isPayPalOrder(string $payPalOrderId): bool
    {
        return $payPalOrderId === $this->payPalOrderId() || $this->isSupersededPayPalOrder($payPalOrderId);
    }

    /** @return array<string, string> */
    public function payPalOrdersToSupersede(): array
    {
        $supersededPayPalOrders = $this->supersededPayPalOrders();
        if ($this->hasPayPalOrderId()) {
            $supersededPayPalOrders[(string) $this->payPalOrderId()] = $this->paymentSource();
        }

        return $supersededPayPalOrders;
    }

    public function withStatus(PayPalPaymentStatus $status): self
    {
        return $this->with([self::STATUS => $status->value]);
    }

    public function withPayPalOrderId(string $payPalOrderId): self
    {
        return $this->with([self::PAYPAL_ORDER_ID => $payPalOrderId]);
    }

    public function withReferenceId(string $referenceId): self
    {
        return $this->with([self::REFERENCE_ID => $referenceId]);
    }

    public function withAmount(int $amount): self
    {
        return $this->with([self::AMOUNT => $amount]);
    }

    public function withPaymentSource(string $paymentSource): self
    {
        return $this->with([self::PAYMENT_SOURCE => $paymentSource]);
    }

    public function withTransactionId(string $transactionId): self
    {
        return $this->with([self::TRANSACTION_ID => $transactionId]);
    }

    public function withPayerAction(string $url): self
    {
        return $this->with([self::PAYER_ACTION_URL => $url]);
    }

    public function withCapturedAmount(?int $amount, ?string $currencyCode): self
    {
        return $this->with([
            self::CAPTURED_AMOUNT => $amount,
            self::CAPTURED_CURRENCY_CODE => $currencyCode,
        ]);
    }

    public function withRefund(?string $refundId): self
    {
        return $this->with([
            self::REFUNDED => true,
            self::REFUND_ID => $refundId,
        ]);
    }

    public function withVault(PayPalVault $vault): self
    {
        return $this->with(array_filter([
            self::VAULT_ID => $vault->id(),
            self::VAULT_STATUS => $vault->status(),
            self::VAULT_CUSTOMER_ID => $vault->customerId(),
            self::PAYER_EMAIL => $vault->payerEmail(),
        ], static fn (?string $value): bool => null !== $value));
    }

    /** @param array<string, string> $supersededPayPalOrders */
    public function withSupersededPayPalOrders(array $supersededPayPalOrders): self
    {
        if ([] === $supersededPayPalOrders) {
            return new self(array_diff_key($this->details, [self::SUPERSEDED_PAYPAL_ORDERS => true]));
        }

        return $this->with([self::SUPERSEDED_PAYPAL_ORDERS => $supersededPayPalOrders]);
    }

    public function withSupersededPayPalOrderRestored(string $payPalOrderId): self
    {
        $supersededPayPalOrders = $this->payPalOrdersToSupersede();
        $paymentSource = $supersededPayPalOrders[$payPalOrderId] ?? PayPalPaymentSourceProviderInterface::PAYPAL;
        unset($supersededPayPalOrders[$payPalOrderId]);

        return (new self(array_diff_key($this->details, array_flip([self::REFERENCE_ID, self::TRANSACTION_ID]))))
            ->withPayPalOrderId($payPalOrderId)
            ->withPaymentSource($paymentSource)
            ->withSupersededPayPalOrders($supersededPayPalOrders)
            ->clearPayerAction()
        ;
    }

    public function clearPayerAction(): self
    {
        return new self(array_diff_key($this->details, array_flip([
            self::PAYER_ACTION_URL,
            self::LEGACY_PAYER_ACTION_RETURN_NONCE,
            self::LEGACY_PAYER_ACTION_CANCEL_NONCE,
        ])));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->details;
    }

    /** @param array<string, mixed> $details */
    private function with(array $details): self
    {
        return new self(array_merge($this->details, $details));
    }

    private function optional(string $key): ?string
    {
        $value = $this->details[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }
}
