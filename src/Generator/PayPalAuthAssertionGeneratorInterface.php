<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Generator;

use Sylius\Component\Core\Model\PaymentMethodInterface;

interface PayPalAuthAssertionGeneratorInterface
{
    public function generate(PaymentMethodInterface $paymentMethod): string;
}
