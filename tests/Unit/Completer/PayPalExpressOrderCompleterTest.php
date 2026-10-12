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

namespace Tests\Sylius\PayPalPlugin\Unit\Completer;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\PayPalPlugin\Completer\PayPalExpressOrderCompleter;
use Sylius\PayPalPlugin\Completer\PayPalExpressOrderCompleterInterface;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;

final class PayPalExpressOrderCompleterTest extends TestCase
{
    private PaymentStateManagerInterface&MockObject $paymentStateManager;

    private StateMachineInterface&MockObject $stateMachine;

    private ObjectManager&MockObject $orderManager;

    private PayPalExpressOrderCompleter $completer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentStateManager = $this->createMock(PaymentStateManagerInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->orderManager = $this->createMock(ObjectManager::class);

        $this->completer = new PayPalExpressOrderCompleter(
            $this->paymentStateManager,
            $this->stateMachine,
            $this->orderManager,
        );
    }

    public function test_it_implements_pay_pal_express_order_completer_interface(): void
    {
        self::assertInstanceOf(PayPalExpressOrderCompleterInterface::class, $this->completer);
    }

    public function test_it_drives_the_payment_through_its_lifecycle_and_completes_the_order(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);

        $this->paymentStateManager->expects(self::once())->method('create')->with($payment);
        $this->paymentStateManager->expects(self::once())->method('process')->with($payment);
        $this->paymentStateManager->expects(self::once())->method('complete')->with($payment);

        $this->stateMachine
            ->expects(self::once())
            ->method('apply')
            ->with($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE)
        ;

        $this->orderManager->expects(self::once())->method('flush');

        $this->completer->complete($order, $payment);
    }
}
