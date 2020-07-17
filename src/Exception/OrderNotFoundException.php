<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Exception;

final class OrderNotFoundException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Order not found');
    }
}
