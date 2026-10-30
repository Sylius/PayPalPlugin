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

final class PaymentMethodAlreadyOnboardedException extends \Exception
{
    public function __construct(string $paymentMethodCode, bool $sandbox)
    {
        parent::__construct(sprintf(
            'The PayPal payment method "%s" already has %s credentials.',
            $paymentMethodCode,
            $sandbox ? 'sandbox' : 'production',
        ));
    }
}
