<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Behat\Service;

use Sylius\Component\Core\Model\PaymentInterface;

/** To not complete PayPal payments by API in Behat scenarios */
final class VoidPayPalPaymentCompleteProcessor
{
    public function completePayPalPayment(PaymentInterface $payment): void
    {
        return;
    }
}
