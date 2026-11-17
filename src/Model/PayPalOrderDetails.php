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

final readonly class PayPalOrderDetails
{
    /**
     * @param array<string, mixed> $paymentSource
     * @param list<PayPalRefund> $refunds
     */
    public function __construct(
        private string $id,
        private ?string $status = null,
        private string $intent = PayPalOrder::INTENT_CAPTURE,
        private ?string $referenceId = null,
        private int $amount = 0,
        private bool $hasItems = false,
        private ?PayPalCapture $capture = null,
        private ?PayPalAuthorization $authorization = null,
        private array $paymentSource = [],
        private ?PayPalShipping $shipping = null,
        private ?Payer $payer = null,
        private array $refunds = [],
    ) {
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

    public function amount(): int
    {
        return $this->amount;
    }

    public function hasItems(): bool
    {
        return $this->hasItems;
    }

    public function capture(): ?PayPalCapture
    {
        return $this->capture;
    }

    /** @return list<PayPalRefund> */
    public function refunds(): array
    {
        return $this->refunds;
    }

    public function completedRefundOf(string $captureId): ?PayPalRefund
    {
        foreach (array_reverse($this->refunds) as $refund) {
            if ($refund->isCompleted() && $captureId === $refund->captureId()) {
                return $refund;
            }
        }

        return null;
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

    public function shipping(): ?PayPalShipping
    {
        return $this->shipping;
    }

    public function payer(): ?Payer
    {
        return $this->payer;
    }

    public function phoneNumber(): ?string
    {
        return $this->shipping?->phoneNumber() ?? $this->payer?->phoneNumber();
    }
}
