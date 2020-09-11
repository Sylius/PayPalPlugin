<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Exception;

final class PayPalApiTimeoutException extends \Exception
{
    public function __construct()
    {
        parent::__construct('PayPal API reached the timeout limit. Please, try again later.');
    }
}
