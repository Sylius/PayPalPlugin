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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Model\OrderItemInterface;

final class PayPalPaymentOnErrorActionTest extends JsonApiTestCase
{
    public function test_it_cancels_the_failed_payment_and_replaces_it_with_exactly_one_new_payment(): void
    {
        $order = $this->processingOrder();

        $this->client->request(
            'POST',
            '/en_US/pay-pal-payment-error',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['error' => 'Google Pay sheet failed', 'payPalOrderId' => 'PAYPAL_ORDER_ID'], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame(['sylius_paypal.something_went_wrong'], $this->client->getRequest()->getSession()->getFlashBag()->peek('error'));

        $reloaded = $this->reloadOrder($order);
        self::assertNotNull($reloaded->getLastPayment(PaymentInterface::STATE_CANCELLED));
        self::assertSame(1, $reloaded->getPayments()->filter(
            static fn (PaymentInterface $payment): bool => in_array($payment->getState(), [PaymentInterface::STATE_NEW, PaymentInterface::STATE_CART], true),
        )->count());
    }

    public function test_it_gives_a_cart_a_new_payment_to_choose_after_the_failed_one(): void
    {
        $order = $this->cartWithProcessingPayment();

        $this->client->request(
            'POST',
            '/en_US/pay-pal-payment-error',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['error' => 'Wallet failed', 'payPalOrderId' => 'PAYPAL_ORDER_ID'], \JSON_THROW_ON_ERROR),
        );

        $reloaded = $this->reloadOrder($order);
        self::assertNotNull($reloaded->getLastPayment(PaymentInterface::STATE_CANCELLED));
        self::assertSame(1, $reloaded->getPayments()->filter(
            static fn (PaymentInterface $payment): bool => PaymentInterface::STATE_CART === $payment->getState(),
        )->count());
    }

    private function cartWithProcessingPayment(): OrderInterface
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/addressed_cart.yaml']);

        /** @var OrderInterface $order */
        $order = $fixtures['addressed_cart'];

        /** @var OrderItemInterface $item */
        $item = $order->getItems()->first();
        $item->setUnitPrice(20);
        $order->recalculateItemsTotal();
        $order->getLastPayment()?->setState(PaymentInterface::STATE_PROCESSING);

        $this->getEntityManager()->flush();

        return $order;
    }

    private function processingOrder(): OrderInterface
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/processing_paypal_order.yaml']);

        /** @var OrderInterface $order */
        $order = $fixtures['processing_order'];

        /** @var OrderItemInterface $item */
        $item = $order->getItems()->first();
        $item->setUnitPrice(20);
        $order->recalculateItemsTotal();

        $this->getEntityManager()->flush();

        return $order;
    }

    private function reloadOrder(OrderInterface $order): OrderInterface
    {
        $manager = self::getContainer()->get('sylius.manager.order');
        $manager->clear();

        /** @var OrderInterface $reloaded */
        $reloaded = self::getContainer()->get('sylius.repository.order')->find($order->getId());

        return $reloaded;
    }
}
