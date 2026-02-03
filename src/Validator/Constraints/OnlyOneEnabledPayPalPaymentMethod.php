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

namespace Sylius\PayPalPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

final class OnlyOneEnabledPayPalPaymentMethod extends Constraint
{
    public string $message = 'sylius_paypal.only_one_paypal_enabled';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return 'sylius_paypal.validator.only_one_enabled_paypal_payment_method';
    }
}
