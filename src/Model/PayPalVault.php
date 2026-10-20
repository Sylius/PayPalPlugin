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

final readonly class PayPalVault
{
    public function __construct(
        private string $status,
        private ?string $id = null,
        private ?string $customerId = null,
        private ?string $payerEmail = null,
    ) {
    }

    /** @param array<string, mixed> $payPalOrderDetails */
    public static function fromPayPalOrder(array $payPalOrderDetails): ?self
    {
        $paypal = $payPalOrderDetails['payment_source']['paypal'] ?? null;
        if (!is_array($paypal)) {
            return null;
        }

        $vault = $paypal['attributes']['vault'] ?? null;
        if (!is_array($vault) || !is_string($vault['status'] ?? null)) {
            return null;
        }

        return new self(
            $vault['status'],
            isset($vault['id']) ? (string) $vault['id'] : null,
            isset($vault['customer']['id']) ? (string) $vault['customer']['id'] : null,
            isset($paypal['email_address']) ? (string) $paypal['email_address'] : null,
        );
    }

    public function status(): string
    {
        return $this->status;
    }

    public function id(): ?string
    {
        return $this->id;
    }

    public function customerId(): ?string
    {
        return $this->customerId;
    }

    public function payerEmail(): ?string
    {
        return $this->payerEmail;
    }
}
