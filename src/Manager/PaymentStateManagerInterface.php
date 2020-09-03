<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Manager;

use Sylius\Component\Core\Model\PaymentInterface;

interface PaymentStateManagerInterface
{
    public function create(PaymentInterface $payment): void;

    public function process(PaymentInterface $payment): void;

    public function complete(PaymentInterface $payment): void;

    public function cancel(PaymentInterface $payment): void;
}
