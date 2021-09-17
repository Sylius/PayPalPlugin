<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Processor;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Resource\Exception\UpdateHandlingException;
use Sylius\PayPalPlugin\Exception\PayPalOrderRefundException;

final class UiPayPalPaymentRefundProcessor implements PaymentRefundProcessorInterface
{
    private PaymentRefundProcessorInterface $paymentRefundProcessor;

    public function __construct(PaymentRefundProcessorInterface $paymentRefundProcessor)
    {
        $this->paymentRefundProcessor = $paymentRefundProcessor;
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
