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
use Payum\Core\Request\Capture;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Creator\PayPalOrderCreatorInterface;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;

final readonly class CaptureAction implements ActionInterface
{
    public function __construct(private PayPalOrderCreatorInterface $payPalOrderCreator)
    {
    }

    /** @param Capture $request */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getModel();

        $this->payPalOrderCreator->create($payment, PayPalPaymentDetails::fromPayment($payment)->paymentSource());
    }

    public function supports($request): bool
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof PaymentInterface
        ;
    }
}
