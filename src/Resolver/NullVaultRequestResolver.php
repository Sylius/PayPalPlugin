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

namespace Sylius\PayPalPlugin\Resolver;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Model\PayPalVaultRequest;

/**
 * Vaults nothing by default. Integrations that need the payment method saved (e.g. subscriptions)
 * decorate the "sylius_paypal.resolver.vault_request" service.
 */
final readonly class NullVaultRequestResolver implements VaultRequestResolverInterface
{
    public function resolve(OrderInterface $order): ?PayPalVaultRequest
    {
        return null;
    }
}
