<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;

interface PaymentProviderInterface
{
    /** @throws PaymentNotFoundException */
    public function getByPayPalOrderId(string $orderId): PaymentInterface;
}
