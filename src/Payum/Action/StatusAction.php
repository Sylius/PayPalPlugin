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
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Model\PayPalPaymentStatus;

final class StatusAction implements ActionInterface
{
    /** @param GetStatus $request */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $details = PayPalPaymentDetails::fromArray(ArrayObject::ensureArrayObject($request->getModel())->toUnsafeArray());

        match ($details->status()) {
            PayPalPaymentStatus::Created => $request->markNew(),
            PayPalPaymentStatus::Captured => $request->markPending(),
            PayPalPaymentStatus::Completed => $request->markCaptured(),
            default => $request->markFailed(),
        };
    }

    public function supports($request): bool
    {
        return
            $request instanceof GetStatus &&
            $request->getFirstModel() instanceof PaymentInterface
        ;
    }
}
