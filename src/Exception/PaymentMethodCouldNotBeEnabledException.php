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

namespace Sylius\PayPalPlugin\Exception;

final class PaymentMethodCouldNotBeEnabledException extends \Exception
{
    public function __construct()
    {
        parent::__construct('PayPal payment method could not be enabled');
    }
}
