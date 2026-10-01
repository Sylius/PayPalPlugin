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

final class ShippingMethodNotAvailableException extends \Exception
{
    public static function withCode(string $code): self
    {
        return new self(sprintf('Shipping method "%s" is not available for the order', $code));
    }
}
