<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Exception;

final class PaymentNotFoundException extends \Exception
{
    public static function withPayPalOrderId(string $payPalOrderId): self
    {
        return new self(sprintf('Payment for PayPal order "%s" could not be found', $payPalOrderId));
    }
}
