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

namespace Tests\Sylius\PayPalPlugin\Functional;

use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\WorkflowInterface;

final class PaymentRefundGuardTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        $eventDispatcher->addListener(
            'workflow.sylius_payment.guard.refund',
            static fn (GuardEvent $event) => $event->setBlocked(false),
        );
    }

    public function test_it_keeps_a_paypal_payment_paypal_has_not_refunded_from_being_refunded_when_another_guard_allows_it(): void
    {
        self::assertFalse($this->paymentWorkflow()->can($this->payPalPayment(['paypal_order_id' => 'ORDER_ID']), PaymentTransitions::TRANSITION_REFUND));
    }

    public function test_it_lets_a_paypal_payment_paypal_has_refunded_be_refunded(): void
    {
        self::assertTrue($this->paymentWorkflow()->can($this->payPalPayment(['paypal_order_id' => 'ORDER_ID', 'refunded' => true]), PaymentTransitions::TRANSITION_REFUND));
    }

    /** @param array<string, mixed> $details */
    private function payPalPayment(array $details): PaymentInterface
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius_paypal');
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        $payment = new Payment();
        $payment->setMethod($paymentMethod);
        $payment->setState(PaymentInterface::STATE_COMPLETED);
        $payment->setDetails($details);

        return $payment;
    }

    private function paymentWorkflow(): WorkflowInterface
    {
        /** @var WorkflowInterface $workflow */
        $workflow = self::getContainer()->get('state_machine.sylius_payment');

        return $workflow;
    }
}
