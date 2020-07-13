<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Resolver;

use Sylius\Component\Core\Model\PaymentInterface;

interface CapturePaymentResolverInterface
{
    public function resolve(PaymentInterface $payment): void;
}
