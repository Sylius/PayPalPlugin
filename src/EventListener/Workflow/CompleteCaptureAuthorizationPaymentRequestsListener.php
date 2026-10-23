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
use Sylius\PayPalPlugin\Completer\CaptureAuthorizationPaymentRequestCompleterInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

final readonly class CompleteCaptureAuthorizationPaymentRequestsListener
{
    public function __construct(private CaptureAuthorizationPaymentRequestCompleterInterface $captureAuthorizationPaymentRequestCompleter)
    {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $payment = $event->getSubject();
        if (
            !$payment instanceof PaymentInterface ||
            !in_array(PaymentInterface::STATE_AUTHORIZED, $event->getTransition()?->getFroms() ?? [], true)
        ) {
            return;
        }

        $this->captureAuthorizationPaymentRequestCompleter->complete($payment);
    }
}
