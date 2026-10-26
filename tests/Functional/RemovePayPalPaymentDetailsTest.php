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

use ApiTestCase\JsonApiTestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\OrderPaymentStates;

final class RemovePayPalPaymentDetailsTest extends JsonApiTestCase
{
    public function test_it_removes_the_paypal_details_when_another_method_is_selected_at_checkout(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/addressed_cart.yaml']);

        /** @var OrderInterface $order */
        $order = $fixtures['addressed_cart'];
        /** @var PaymentMethodInterface $cashOnDelivery */
        $cashOnDelivery = $fixtures['cash_on_delivery'];
        $order->getLastPayment()?->setMethod($cashOnDelivery);

        /** @var StateMachineInterface $stateMachine */
        $stateMachine = self::getContainer()->get('sylius_abstraction.state_machine');
        $stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
        $this->getEntityManager()->flush();

        self::getContainer()->get('sylius.manager.order')->clear();
        /** @var OrderInterface $reloaded */
        $reloaded = self::getContainer()->get('sylius.repository.order')->find($order->getId());
        $payment = $reloaded->getLastPayment();
        self::assertNotNull($payment);
        self::assertSame('CASH_ON_DELIVERY', $payment->getMethod()?->getCode());
        self::assertSame([], $payment->getDetails());
    }

    public function test_it_keeps_the_paypal_details_when_paypal_is_selected_again_at_checkout(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/addressed_cart.yaml']);

        /** @var OrderInterface $order */
        $order = $fixtures['addressed_cart'];

        /** @var StateMachineInterface $stateMachine */
        $stateMachine = self::getContainer()->get('sylius_abstraction.state_machine');
        $stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);
        $this->getEntityManager()->flush();

        self::getContainer()->get('sylius.manager.order')->clear();
        /** @var OrderInterface $reloaded */
        $reloaded = self::getContainer()->get('sylius.repository.order')->find($order->getId());
        self::assertSame(['paypal_order_id' => 'PAYPAL_ORDER_ID'], $reloaded->getLastPayment()?->getDetails());
    }

    public function test_it_removes_the_paypal_details_when_another_method_is_chosen_on_the_order_page(): void
    {
        /** @var OrderInterface $order */
        $order = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml'])['new_order'];
        $order->setPaymentState(OrderPaymentStates::STATE_AWAITING_PAYMENT);
        $order->getLastPayment()?->setDetails(['status' => 'CAPTURED', 'paypal_order_id' => 'PAYPAL_ORDER_ID', 'payment_source' => 'paypal']);
        $this->getEntityManager()->flush();

        $crawler = $this->client->request('GET', '/en_US/order/TOKEN');
        $form = $crawler->filter('form[name="sylius_checkout_select_payment"]')->form();
        $this->client->submit($form, ['sylius_checkout_select_payment[payments][0][method]' => 'CASH_ON_DELIVERY']);

        self::assertSame(302, $this->client->getResponse()->getStatusCode());

        self::getContainer()->get('sylius.manager.order')->clear();
        /** @var OrderInterface $reloaded */
        $reloaded = self::getContainer()->get('sylius.repository.order')->find($order->getId());
        $payment = $reloaded->getLastPayment();
        self::assertNotNull($payment);
        self::assertSame('CASH_ON_DELIVERY', $payment->getMethod()?->getCode());
        self::assertSame([], $payment->getDetails());
    }
}
