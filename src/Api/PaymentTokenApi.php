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

final readonly class PaymentTokenApi implements PaymentTokenApiInterface
{
    public function __construct(private PayPalClientInterface $client)
    {
    }

    public function delete(PaymentMethodInterface $paymentMethod, string $vaultId): void
    {
        $this->client->delete($paymentMethod, sprintf('v3/vault/payment-tokens/%s', rawurlencode($vaultId)));
    }
}
