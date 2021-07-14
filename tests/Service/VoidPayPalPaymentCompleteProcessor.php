<?php

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Service;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Processor\PaymentCompleteProcessorInterface;

/** To not complete PayPal payments by API in Behat scenarios */
final class VoidPayPalPaymentCompleteProcessor implements PaymentCompleteProcessorInterface
{
    public function completePayment(PaymentInterface $payment): void
    {
        return;
    }
}
