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
use Tests\Sylius\PayPalPlugin\Service\DummyOrderDetailsApi;

final class PayPalRedirectCancelActionTest extends JsonApiTestCase
{
    private const CANCEL_NONCE = 'fedcba9876543210fedcba9876543210';

    protected function setUp(): void
    {
        parent::setUp();
        DummyOrderDetailsApi::$captureStatus = 'PENDING';
        DummyOrderDetailsApi::$failWith = null;
    }

    public function test_it_cancels_the_attempt_the_payer_walked_away_from(): void
    {
        $order = $this->redirectOrder();

        $this->cancel(self::CANCEL_NONCE);
        $reloaded = $this->reloadOrder($order);

        self::assertNotNull($reloaded->getLastPayment(PaymentInterface::STATE_CANCELLED));
        self::assertNull($reloaded->getLastPayment(PaymentInterface::STATE_COMPLETED));
        self::assertStringEndsWith('/en_US/order/TOKEN', $this->location());
    }

    public function test_it_keeps_a_payment_the_bank_let_through_after_all(): void
    {
        DummyOrderDetailsApi::$captureStatus = 'COMPLETED';
        $order = $this->redirectOrder();

        $this->cancel(self::CANCEL_NONCE);
        $reloaded = $this->reloadOrder($order);

        self::assertNull($reloaded->getLastPayment(PaymentInterface::STATE_CANCELLED));
        self::assertNotNull($reloaded->getLastPayment(PaymentInterface::STATE_COMPLETED));
        self::assertStringEndsWith('/en_US/order/thank-you', $this->location());
    }

    public function test_it_cancels_nothing_the_second_time_the_payer_comes_back(): void
    {
        $order = $this->redirectOrder();

        $this->cancel(self::CANCEL_NONCE);
        $this->cancel(self::CANCEL_NONCE);
        $reloaded = $this->reloadOrder($order);

        self::assertTrue($this->client->getResponse()->isRedirect());
        self::assertCount(
            1,
            $reloaded->getPayments()->filter(
                static fn (PaymentInterface $payment): bool => PaymentInterface::STATE_CANCELLED === $payment->getState(),
            ),
        );
    }

    private function location(): string
    {
        return (string) $this->client->getResponse()->headers->get('Location');
    }

    private function cancel(string $nonce): void
    {
        $this->client->request('GET', sprintf('/en_US/paypal/redirect-cancel/TOKEN/%s', $nonce));
    }

    private function redirectOrder(): OrderInterface
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/redirect_paypal_order.yaml']);

        /** @var OrderInterface $order */
        $order = $fixtures['redirect_order'];

        /** @var OrderItemInterface $item */
        $item = $order->getItems()->first();
        $item->setUnitPrice(20);
        $order->recalculateItemsTotal();

        self::getContainer()->get('sylius.manager.order')->flush();

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
