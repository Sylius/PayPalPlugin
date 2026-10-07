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

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;

final readonly class PayPalPaymentCompleteProcessor implements PaymentCompleteProcessorInterface
{
    public function __construct(
        private PaymentCaptureProcessorInterface $paymentCaptureProcessor,
        private LoggerInterface $logger,
    ) {
    }

    public function completePayment(PaymentInterface $payment): void
    {
        $details = PayPalPaymentDetails::fromPayment($payment);
        if (!$details->hasPayPalOrderId()) {
            return;
        }

        $paymentSource = $details->paymentSource();
        if (null !== RedirectPaymentSource::tryFrom($paymentSource)) {
            $this->logger->warning(sprintf(
                'A "%s" PayPal order is captured by PayPal on payment approval and must not be completed here.',
                $paymentSource,
            ));

            return;
        }

        $this->paymentCaptureProcessor->capture($payment);
    }
}
