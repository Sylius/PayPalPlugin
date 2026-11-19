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

namespace Sylius\PayPalPlugin\Processor;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Resource\Exception\UpdateHandlingException;
use Sylius\PayPalPlugin\Exception\PayPalOrderRefundException;

/** @deprecated since Sylius/PayPalPlugin 2.2 and will be removed in Sylius/PayPalPlugin 3.0. */
final readonly class UiPayPalPaymentRefundProcessor implements PaymentRefundProcessorInterface
{
    public function __construct(private PaymentRefundProcessorInterface $paymentRefundProcessor)
    {
    }

    public function refund(PaymentInterface $payment): void
    {
        try {
            $this->paymentRefundProcessor->refund($payment);
        } catch (PayPalOrderRefundException $exception) {
            throw new UpdateHandlingException($exception->getMessage());
        }
    }
}
