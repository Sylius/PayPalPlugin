<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Exception;

final class PayPalPaymentMethodNotFoundException extends \Exception
{
    public function __construct()
    {
        parent::__construct('PayPal payment method not found');
    }
}
