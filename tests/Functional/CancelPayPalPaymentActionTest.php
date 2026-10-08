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

final class CancelPayPalPaymentActionTest extends JsonApiTestCase
{
    public function test_it_gives_a_cart_a_new_payment_to_choose_after_the_buyer_closed_the_wallet(): void
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

        $this->client->request(
            'POST',
            '/en_US/cancel-pay-pal-payment',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['payPalOrderId' => 'PAYPAL_ORDER_ID'], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(204, $this->client->getResponse()->getStatusCode());

        self::getContainer()->get('sylius.manager.order')->clear();
        /** @var OrderInterface $reloaded */
        $reloaded = self::getContainer()->get('sylius.repository.order')->find($order->getId());
        self::assertNotNull($reloaded->getLastPayment(PaymentInterface::STATE_CANCELLED));
        self::assertSame(1, $reloaded->getPayments()->filter(
            static fn (PaymentInterface $payment): bool => PaymentInterface::STATE_CART === $payment->getState(),
        )->count());
    }
}
