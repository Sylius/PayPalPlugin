<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Updater;

use Sylius\Component\Core\Model\PaymentInterface;

interface PaymentUpdaterInterface
{
    public function updateAmount(PaymentInterface $payment, int $newAmount): void;
}
