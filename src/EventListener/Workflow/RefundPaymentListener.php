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

namespace Sylius\PayPalPlugin\EventListener\Workflow;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Processor\PaymentRefundProcessorInterface;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Webmozart\Assert\Assert;

/** @deprecated since Sylius/PayPalPlugin 2.2 and will be removed in Sylius/PayPalPlugin 3.0. */
final class RefundPaymentListener
{
    public function __construct(private readonly PaymentRefundProcessorInterface $paymentRefundProcessor)
    {
    }

    public function __invoke(TransitionEvent $event): void
    {
        $payment = $event->getSubject();
        Assert::isInstanceOf($payment, PaymentInterface::class);

        $this->paymentRefundProcessor->refund($payment);
    }
}
