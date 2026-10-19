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

use Sylius\Component\Payment\Model\PaymentMethodInterface;

interface PaymentAuthorizationCheckerInterface
{
    public function authorizesPayments(PaymentMethodInterface $paymentMethod): bool;

    public function authorizes(PaymentMethodInterface $paymentMethod, string $paymentSource): bool;
}
