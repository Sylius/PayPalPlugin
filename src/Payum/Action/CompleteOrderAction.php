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

namespace Sylius\PayPalPlugin\Payum\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;
use Sylius\PayPalPlugin\Payum\Request\CompleteOrder;
use Sylius\PayPalPlugin\Processor\PaymentCaptureProcessorInterface;

final readonly class CompleteOrderAction implements ActionInterface
{
    public function __construct(
        private PaymentCaptureProcessorInterface $paymentCaptureProcessor,
        private LoggerInterface $logger,
    ) {
    }

    /** @param CompleteOrder $request */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getModel();

        $paymentSource = PayPalPaymentDetails::fromPayment($payment)->paymentSource();
        if (null !== RedirectPaymentSource::tryFrom($paymentSource)) {
            $this->logger->warning(sprintf(
                'A "%s" PayPal order is captured by PayPal on payment approval and must not be completed here.',
                $paymentSource,
            ));

            return;
        }

        $this->paymentCaptureProcessor->capture($payment);
    }

    public function supports($request): bool
    {
        return
            $request instanceof CompleteOrder &&
            $request->getModel() instanceof PaymentInterface
        ;
    }
}
