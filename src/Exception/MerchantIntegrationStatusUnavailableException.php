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

final class MerchantIntegrationStatusUnavailableException extends \RuntimeException
{
    public function __construct(string $paymentMethodCode, \Throwable $previous)
    {
        parent::__construct(
            sprintf('Could not read the PayPal merchant integration status of the "%s" payment method: %s', $paymentMethodCode, $previous->getMessage()),
            0,
            $previous,
        );
    }
}
