<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Enabler;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Exception\PaymentMethodCouldNotBeEnabledException;

interface PaymentMethodEnablerInterface
{
    /**
     * @throws PaymentMethodCouldNotBeEnabledException
     */
    public function enable(PaymentMethodInterface $paymentMethod): void;
}
