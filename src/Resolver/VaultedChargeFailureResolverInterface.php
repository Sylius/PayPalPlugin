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

use Sylius\PayPalPlugin\Model\VaultedChargeFailure;

interface VaultedChargeFailureResolverInterface
{
    /** @param array<string> $issues */
    public function resolveFromIssues(array $issues): VaultedChargeFailure;

    /** @param array<string, mixed> $payPalOrder */
    public function resolveFromPayPalOrder(array $payPalOrder): VaultedChargeFailure;
}
