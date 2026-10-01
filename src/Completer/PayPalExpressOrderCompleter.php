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

namespace Sylius\PayPalPlugin\Completer;

use Doctrine\Persistence\ObjectManager;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;

final readonly class PayPalExpressOrderCompleter implements PayPalExpressOrderCompleterInterface
{
    public function __construct(
        private PaymentStateManagerInterface $paymentStateManager,
        private StateMachineInterface $stateMachine,
        private ObjectManager $orderManager,
    ) {
    }

    public function complete(OrderInterface $order, PaymentInterface $payment): void
    {
        $this->paymentStateManager->create($payment);
        $this->paymentStateManager->process($payment);
        $this->paymentStateManager->complete($payment);

        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);

        $this->orderManager->flush();
    }
}
