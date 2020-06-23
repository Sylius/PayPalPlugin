<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Manager;

use Sylius\Component\Core\Model\PaymentInterface;

interface PaymentStateManagerInterface
{
    public function changeState(PaymentInterface $payment, string $targetState): void;
}
