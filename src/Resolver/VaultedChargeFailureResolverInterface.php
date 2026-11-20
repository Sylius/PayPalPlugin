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

use Sylius\PayPalPlugin\Model\PayPalOrderDetails;
use Sylius\PayPalPlugin\Model\VaultedChargeFailure;

interface VaultedChargeFailureResolverInterface
{
    /** @param array<string> $issues */
    public function resolveFromIssues(array $issues): VaultedChargeFailure;

    public function resolveFromPayPalOrder(PayPalOrderDetails $payPalOrder): VaultedChargeFailure;
}
