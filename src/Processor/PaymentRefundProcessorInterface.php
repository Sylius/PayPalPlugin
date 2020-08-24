<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Processor;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Exception\PayPalOrderRefundException;

interface PaymentRefundProcessorInterface
{
    /**
     * @throws PayPalOrderRefundException
     */
    public function refund(PaymentInterface $payment): void;
}
