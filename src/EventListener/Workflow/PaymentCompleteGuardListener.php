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
use Sylius\PayPalPlugin\Checker\PaymentCompletionCheckerInterface;
use Symfony\Component\Workflow\Event\GuardEvent;

final readonly class PaymentCompleteGuardListener
{
    public function __construct(private PaymentCompletionCheckerInterface $paymentCompletionChecker)
    {
    }

    public function __invoke(GuardEvent $event): void
    {
        $payment = $event->getSubject();
        if (!$payment instanceof PaymentInterface || $this->paymentCompletionChecker->canBeCompleted($payment)) {
            return;
        }

        $event->setBlocked(true, 'PayPal has not confirmed the capture of the authorized payment.');
    }
}
