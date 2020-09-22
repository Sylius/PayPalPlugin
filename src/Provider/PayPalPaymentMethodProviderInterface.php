<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Exception\PayPalPaymentMethodNotFoundException;

interface PayPalPaymentMethodProviderInterface
{
    /** @throws PayPalPaymentMethodNotFoundException */
    public function provide(): PaymentMethodInterface;
}
