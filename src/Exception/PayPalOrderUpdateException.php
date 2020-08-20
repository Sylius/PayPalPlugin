<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Exception;

final class PayPalOrderUpdateException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Could not update PayPal order');
    }
}
