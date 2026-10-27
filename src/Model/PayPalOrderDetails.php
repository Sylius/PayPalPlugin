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
     * @param array<string, mixed>|null $shippingAddress
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
}
