<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Exception;

final class PayPalWrongWebhookException extends \Exception
{
    public function __construct()
    {
        parent::__construct('PayPal webhook not valid');
    }
}
