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
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Order\Model\OrderItemInterface;

final class RedirectCancelActionTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    private const CANCEL_NONCE = 'fedcba9876543210fedcba9876543210';

    public function test_it_cancels_the_attempt_the_payer_walked_away_from(): void
    {
        $order = $this->redirectOrder();
        $this->payPalApi()->mockOrderDetailsWithCapture(captureStatus: 'PENDING');

        $this->cancel(self::CANCEL_NONCE);
        $reloaded = $this->reloadOrder($order);

        self::assertNotNull($reloaded->getLastPayment(PaymentInterface::STATE_CANCELLED));
        self::assertNull($reloaded->getLastPayment(PaymentInterface::STATE_COMPLETED));
        self::assertSame('/en_US/order/TOKEN/pay', $this->location());
    }

    public function test_it_keeps_a_payment_the_bank_let_through_after_all(): void
    {
        $order = $this->redirectOrder();
        $this->payPalApi()->mockOrderDetailsWithCapture();

        $this->cancel(self::CANCEL_NONCE);
        $reloaded = $this->reloadOrder($order);

        self::assertNull($reloaded->getLastPayment(PaymentInterface::STATE_CANCELLED));
        self::assertNotNull($reloaded->getLastPayment(PaymentInterface::STATE_COMPLETED));
        self::assertStringEndsWith('/en_US/order/thank-you', $this->location());
    }

    public function test_it_cancels_nothing_the_second_time_the_payer_comes_back(): void
    {
        $order = $this->redirectOrder();
        $this->payPalApi()->mockOrderDetailsWithCapture(captureStatus: 'PENDING');

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

    public function test_it_fails_the_attempt_the_bank_refused(): void
    {
        $order = $this->redirectOrder();
        $this->payPalApi()->mockOrderDetailsWithCapture(captureStatus: 'PENDING');

        $this->cancel(self::CANCEL_NONCE, '?errorcode=processing_error');
        $reloaded = $this->reloadOrder($order);

        self::assertNotNull($reloaded->getLastPayment(PaymentInterface::STATE_FAILED));
        self::assertNull($reloaded->getLastPayment(PaymentInterface::STATE_CANCELLED));
        self::assertSame(1, $this->newPayments($reloaded));
        self::assertSame(['sylius_paypal.something_went_wrong'], $this->client->getRequest()->getSession()->getFlashBag()->peek('error'));
    }

    public function test_it_cancels_the_attempt_the_payer_cancelled_at_the_bank(): void
    {
        $order = $this->redirectOrder();
        $this->payPalApi()->mockOrderDetailsWithCapture(captureStatus: 'PENDING');

        $this->cancel(self::CANCEL_NONCE, '?errorcode=payment_error');
        $reloaded = $this->reloadOrder($order);

        self::assertNotNull($reloaded->getLastPayment(PaymentInterface::STATE_CANCELLED));
        self::assertNull($reloaded->getLastPayment(PaymentInterface::STATE_FAILED));
        self::assertSame(['sylius_paypal.payment_cancelled'], $this->client->getRequest()->getSession()->getFlashBag()->peek('info'));
    }

    public function test_it_shows_the_payer_the_way_back_to_the_bank_on_the_order_page(): void
    {
        $this->redirectOrder();

        $this->client->request('GET', '/en_US/order/TOKEN');
        $content = (string) $this->client->getResponse()->getContent();

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('We are waiting for your bank', $content);
        self::assertStringContainsString('href="https://www.sandbox.paypal.com/payment/trustly?token=PAYPAL_ORDER_ID"', $content);
        self::assertStringNotContainsString('name="sylius_checkout_select_payment"', $content);
    }

    public function test_it_keeps_the_payment_form_on_the_order_page_when_nothing_awaits_the_bank(): void
    {
        $order = $this->redirectOrder();
        $order->setPaymentState(OrderPaymentStates::STATE_AWAITING_PAYMENT);
        $payment = $order->getLastPayment();
        $payment?->setDetails(array_diff_key($payment->getDetails(), ['payer_action_url' => true]));
        $this->getEntityManager()->flush();

        $this->client->request('GET', '/en_US/order/TOKEN');
        $content = (string) $this->client->getResponse()->getContent();

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringNotContainsString('We are waiting for your bank', $content);
        self::assertStringContainsString('name="sylius_checkout_select_payment"', $content);
    }

    public function test_it_replaces_a_cancelled_attempt_with_exactly_one_new_payment(): void
    {
        $order = $this->redirectOrder();
        $this->payPalApi()->mockOrderDetailsWithCapture(captureStatus: 'PENDING');

        $this->cancel(self::CANCEL_NONCE);

        self::assertSame(1, $this->newPayments($this->reloadOrder($order)));

        $this->client->request('GET', '/en_US/order/TOKEN');
        self::assertStringContainsString('name="sylius_checkout_select_payment"', (string) $this->client->getResponse()->getContent());
    }

    private function newPayments(OrderInterface $order): int
    {
        return $order->getPayments()->filter(
            static fn (PaymentInterface $payment): bool => in_array($payment->getState(), [PaymentInterface::STATE_NEW, PaymentInterface::STATE_CART], true),
        )->count();
    }

    private function location(): string
    {
        return (string) $this->client->getResponse()->headers->get('Location');
    }

    private function cancel(string $nonce, string $query = ''): void
    {
        $this->client->request('GET', sprintf('/en_US/paypal/redirect-cancel/TOKEN/%s%s', $nonce, $query));
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
