<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Processor;

use Sylius\Component\Core\Model\PaymentInterface;

interface PaymentCompleteProcessorInterface
{
    public function completePayment(PaymentInterface $payment): void;
}
