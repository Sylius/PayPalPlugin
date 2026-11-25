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

namespace Sylius\PayPalPlugin\Checker;

use Sylius\Component\Core\Model\PaymentInterface;

/**
 * @internal
 *
 * @deprecated since Sylius/PayPalPlugin 3.0 and will be removed in Sylius/PayPalPlugin 4.0. It only serves the PayPal payments started before the upgrade to 3.0.
 */
interface PreUpgradePayPalOrderCheckerInterface
{
    public function isAwaitingPreUpgradePayPalOrder(PaymentInterface $payment): bool;
}
