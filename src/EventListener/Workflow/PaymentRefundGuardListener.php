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
use Sylius\PayPalPlugin\Checker\PaymentRefundCheckerInterface;
use Symfony\Component\Workflow\Event\GuardEvent;

final readonly class PaymentRefundGuardListener
{
    public function __construct(private PaymentRefundCheckerInterface $paymentRefundChecker)
    {
    }

    public function __invoke(GuardEvent $event): void
    {
        $payment = $event->getSubject();
        if (!$payment instanceof PaymentInterface || $this->paymentRefundChecker->canBeRefunded($payment)) {
            return;
        }

        $event->setBlocked(true, 'PayPal has not confirmed the refund of the payment.');
    }
}
