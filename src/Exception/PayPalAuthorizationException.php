<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Exception;

final class PayPalAuthorizationException extends \Exception
{
    public function __construct()
    {
        parent::__construct('PayPal client could not be authorized');
    }
}
