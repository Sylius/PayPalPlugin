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

namespace Sylius\PayPalPlugin\Manager;

use Doctrine\Persistence\ObjectManager;
use SM\Factory\FactoryInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Abstraction\StateMachine\WinzouStateMachineAdapter;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Processor\PaymentCompleteProcessorInterface;

final class PaymentStateManager implements PaymentStateManagerInterface
{
    public function __construct(
        private readonly FactoryInterface|StateMachineInterface $stateMachineFactory,
        private readonly ObjectManager $paymentManager,
        private readonly PaymentCompleteProcessorInterface $paypalPaymentCompleteProcessor,
    ) {
        if ($this->stateMachineFactory instanceof FactoryInterface) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                sprintf(
                    'Passing an instance of "%s" as the first argument is deprecated and will be prohibited in 2.0. Use "%s" instead.',
                    FactoryInterface::class,
                    StateMachineInterface::class,
                ),
            );
        }
    }

    public function create(PaymentInterface $payment): void
    {
        $this->applyTransitionAndSave($payment, PaymentTransitions::TRANSITION_CREATE);
    }

    public function process(PaymentInterface $payment): void
    {
        $this->applyTransitionAndSave($payment, PaymentTransitions::TRANSITION_PROCESS);
    }

    public function complete(PaymentInterface $payment): void
    {
        // TODO - move target state resolving to the separate service
        $this->paypalPaymentCompleteProcessor->completePayment($payment);

        $status = (string) $payment->getDetails()['status'];
        if ($status === StatusAction::STATUS_COMPLETED) {
            $this->applyTransitionAndSave($payment, PaymentTransitions::TRANSITION_COMPLETE);

            return;
        }

        if (
            $status === StatusAction::STATUS_PROCESSING &&
            $payment->getState() !== PaymentInterface::STATE_PROCESSING
        ) {
            $this->applyTransitionAndSave($payment, PaymentTransitions::TRANSITION_PROCESS);
        }
    }

    public function cancel(PaymentInterface $payment): void
    {
        $this->applyTransitionAndSave($payment, PaymentTransitions::TRANSITION_CANCEL);
    }

    private function applyTransitionAndSave(PaymentInterface $payment, string $transition): void
    {
        $this->getStateMachine()->apply($payment, PaymentTransitions::GRAPH, $transition);
        $this->paymentManager->flush();
    }

    private function getStateMachine(): StateMachineInterface
    {
        if ($this->stateMachineFactory instanceof FactoryInterface) {
            return new WinzouStateMachineAdapter($this->stateMachineFactory);
        }

        return $this->stateMachineFactory;
    }
}
