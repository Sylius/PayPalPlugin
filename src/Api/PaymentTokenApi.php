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

namespace Sylius\PayPalPlugin\Api;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiNotFoundException;

final readonly class PaymentTokenApi implements PaymentTokenApiInterface
{
    public function __construct(private PayPalClientInterface $client)
    {
    }

    public function delete(PaymentMethodInterface $paymentMethod, string $vaultId): void
    {
        $this->client->delete($paymentMethod, $this->path($vaultId));
    }

    public function exists(PaymentMethodInterface $paymentMethod, string $vaultId): bool
    {
        try {
            $this->client->get($paymentMethod, $this->path($vaultId));
        } catch (PayPalApiNotFoundException) {
            return false;
        }

        return true;
    }

    private function path(string $vaultId): string
    {
        return sprintf('v3/vault/payment-tokens/%s', rawurlencode($vaultId));
    }
}
