<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Payum\Request;

use Payum\Core\Request\Generic;

class CompleteOrder extends Generic
{
    /** @var string */
    private $orderId;

    public function __construct($model, string $orderId)
    {
        parent::__construct($model);

        $this->orderId = $orderId;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }
}
