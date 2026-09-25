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
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Processor\PaymentCompleteProcessorInterface;

/**
 * To not complete PayPal payments by API in Behat scenarios, by default - a scenario that
 * specifically needs to reach a completed payment can opt in via completeSuccessfullyNext().
 */
final class VoidPayPalPaymentCompleteProcessor implements PaymentCompleteProcessorInterface
{
    private bool $completeSuccessfully = false;

    public function completeSuccessfullyNext(): void
    {
        $this->completeSuccessfully = true;
    }

    public function completePayment(PaymentInterface $payment): void
    {
        if (!$this->completeSuccessfully) {
            return;
        }

        $payment->setDetails(array_merge($payment->getDetails(), ['status' => StatusAction::STATUS_COMPLETED]));
    }
}
