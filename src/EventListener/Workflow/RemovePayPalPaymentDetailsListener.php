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

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Remover\PayPalPaymentDetailsRemoverInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

final readonly class RemovePayPalPaymentDetailsListener
{
    public function __construct(private PayPalPaymentDetailsRemoverInterface $payPalPaymentDetailsRemover)
    {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();
        if (!$order instanceof OrderInterface) {
            return;
        }

        $this->payPalPaymentDetailsRemover->removeFromOrder($order);
    }
}
