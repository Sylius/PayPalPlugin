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

final class PayPalWebhookNotRegisteredException extends \RuntimeException
{
    public function __construct(string $paymentMethodCode)
    {
        parent::__construct(sprintf(
            'PayPal has no webhook registered for the payment method "%s".',
            $paymentMethodCode,
        ));
    }
}
