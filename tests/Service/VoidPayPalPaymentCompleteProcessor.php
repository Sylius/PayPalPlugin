<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
