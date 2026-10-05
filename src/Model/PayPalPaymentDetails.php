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

    public const ORDER_ID = 'paypal_order_id';

    public const REFERENCE_ID = 'reference_id';

    public const AMOUNT = 'payment_amount';

    public const PAYMENT_SOURCE = 'payment_source';

    public const TRANSACTION_ID = 'transaction_id';

    public const PAYER_ACTION_URL = 'payer_action_url';

    public const PAYER_ACTION_RETURN_NONCE = 'payer_action_return_nonce';

    public const PAYER_ACTION_CANCEL_NONCE = 'payer_action_cancel_nonce';

    public const CAPTURED_AMOUNT = 'captured_amount';

    public const CAPTURED_CURRENCY_CODE = 'captured_currency_code';

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

    public function orderId(): ?string
    {
        return $this->optional(self::ORDER_ID);
    }

    public function hasOrderId(): bool
    {
        return null !== $this->orderId() && '' !== $this->orderId();
    }

    public function referenceId(): ?string
    {
        return $this->optional(self::REFERENCE_ID);
    }

    public function amount(): ?int
    {
        $amount = $this->details[self::AMOUNT] ?? null;

        return is_numeric($amount) ? (int) $amount : null;
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

    public function payerActionReturnNonce(): ?string
    {
        return $this->optional(self::PAYER_ACTION_RETURN_NONCE);
    }

    public function payerActionCancelNonce(): ?string
    {
        return $this->optional(self::PAYER_ACTION_CANCEL_NONCE);
    }

    public function withStatus(PayPalPaymentStatus $status): self
    {
        return $this->with([self::STATUS => $status->value]);
    }

    public function withOrderId(string $orderId): self
    {
        return $this->with([self::ORDER_ID => $orderId]);
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

    public function withPayerAction(string $url, string $returnNonce, string $cancelNonce): self
    {
        return $this->with([
            self::PAYER_ACTION_URL => $url,
            self::PAYER_ACTION_RETURN_NONCE => $returnNonce,
            self::PAYER_ACTION_CANCEL_NONCE => $cancelNonce,
        ]);
    }

    public function withoutPayerAction(): self
    {
        return new self(array_diff_key($this->details, array_flip([
            self::PAYER_ACTION_URL,
            self::PAYER_ACTION_RETURN_NONCE,
            self::PAYER_ACTION_CANCEL_NONCE,
        ])));
    }

    public function withCapturedAmountMismatch(?int $amount, ?string $currencyCode): self
    {
        return $this->with([
            self::CAPTURED_AMOUNT => $amount,
            self::CAPTURED_CURRENCY_CODE => $currencyCode,
        ]);
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
