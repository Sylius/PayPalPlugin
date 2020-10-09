<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Exception;

final class PayPalWebhookAlreadyExistsException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Webhook already registered!');
    }
}
