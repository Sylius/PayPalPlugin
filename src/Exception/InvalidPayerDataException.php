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

final class InvalidPayerDataException extends \Exception
{
    public static function withoutBillingAddress(string $paymentSource): self
    {
        return new self(sprintf('The PayPal order needs a billing address to be paid with "%s"', $paymentSource));
    }

    public static function withoutPayerName(string $paymentSource): self
    {
        return new self(sprintf('The PayPal order needs the payer name to be paid with "%s"', $paymentSource));
    }

    public static function withoutPayerEmail(string $paymentSource): self
    {
        return new self(sprintf('The PayPal order needs the payer email to be paid with "%s"', $paymentSource));
    }

    public static function withCountryCode(string $paymentSource, string $countryCode): self
    {
        return new self(sprintf(
            'PayPal does not accept the country code "%s" of an order paid with "%s"',
            $countryCode,
            $paymentSource,
        ));
    }
}
