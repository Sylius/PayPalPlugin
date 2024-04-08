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

namespace Sylius\PayPalPlugin\Payum\Request;

use Payum\Core\Request\Generic;

class CompleteOrder extends Generic
{
    private string $orderId;

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
