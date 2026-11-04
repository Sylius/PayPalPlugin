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

namespace Sylius\PayPalPlugin\Completer;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Exception\PaymentAmountMismatchException;

interface ExpressCheckoutCompleterInterface
{
    /** @throws PaymentAmountMismatchException */
    public function complete(PaymentInterface $payment): void;
}
