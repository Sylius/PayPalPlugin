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
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Processor\PaymentCompleteProcessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CompletePayPalOrderFromPaymentPageActionTest extends JsonApiTestCase
{
    private const UNIT_PRICE = 20;

    public function test_it_completes_the_order_when_the_amount_matches(): void
    {
        [$orderId] = $this->loadPaymentPageOrder(amountMatches: true);
        $this->mockSuccessfulPaymentCompleteProcessor();

        $content = $this->completePayPalOrder($orderId);
        $order = $this->refreshOrder($orderId);

        self::assertSame($this->generateUrl('sylius_shop_order_thank_you'), $content['return_url']);
        self::assertSame('completed', $order->getCheckoutState());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $order->getLastPayment()->getState());
    }

    public function test_it_recreates_a_cart_payment_when_the_amount_does_not_match(): void
    {
        [$orderId, $originalPaymentId] = $this->loadPaymentPageOrder(amountMatches: false);

        $content = $this->completePayPalOrder($orderId);
        $order = $this->refreshOrder($orderId);

        self::assertSame($this->generateUrl('sylius_shop_checkout_complete'), $content['return_url']);

        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);
        self::assertNotNull($payment);
        self::assertNotSame($originalPaymentId, $payment->getId());
        self::assertSame($order->getTotal(), $payment->getAmount());
        self::assertSame('PAYPAL', $payment->getMethod()->getCode());
    }

    public function test_it_keeps_the_cancelled_payment_on_the_order_when_the_amount_does_not_match(): void
    {
        [$orderId, $originalPaymentId] = $this->loadPaymentPageOrder(amountMatches: false);

        $this->completePayPalOrder($orderId);
        $order = $this->refreshOrder($orderId);

        $payment = $order->getLastPayment(PaymentInterface::STATE_CANCELLED);
        self::assertNotNull($payment);
        self::assertSame($originalPaymentId, $payment->getId());
        self::assertSame('PAYPAL_ORDER_ID', $payment->getDetails()['paypal_order_id']);
    }

    public function test_it_does_not_complete_the_order_when_the_amount_does_not_match(): void
    {
        [$orderId] = $this->loadPaymentPageOrder(amountMatches: false);

        $this->completePayPalOrder($orderId);

        self::assertNotSame('completed', $this->refreshOrder($orderId)->getCheckoutState());
    }

    public function test_it_tells_the_buyer_why_the_payment_was_not_taken(): void
    {
        [$orderId] = $this->loadPaymentPageOrder(amountMatches: false);

        $this->completePayPalOrder($orderId);

        $flashes = $this->client->getRequest()->getSession()->getBag('flashes')->peekAll();
        self::assertSame(['sylius_paypal.order_total_changed'], $flashes['error'] ?? []);
    }

    /** @return array{0: int, 1: int} */
    private function loadPaymentPageOrder(bool $amountMatches): array
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/payment_page_order.yaml']);
        $orderId = (int) $fixtures['payment_page_order']->getId();

        /** @var OrderInterface $order */
        $order = self::getContainer()->get('sylius.repository.order')->find($orderId);

        /** @var OrderItemInterface $item */
        $item = $order->getItems()->first();
        $item->setUnitPrice(self::UNIT_PRICE);
        $order->recalculateItemsTotal();

        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment();
        $payment->setAmount($order->getTotal());
        $payment->setDetails(array_merge(
            $payment->getDetails(),
            ['payment_amount' => $amountMatches ? $order->getTotal() : $order->getTotal() - self::UNIT_PRICE],
        ));

        self::getContainer()->get('sylius.manager.order')->flush();

        return [$orderId, (int) $payment->getId()];
    }

    /** @return array<string, mixed> */
    private function completePayPalOrder(int $orderId): array
    {
        $this->client->request(
            'POST',
            '/en_US/pay-pal-order-payment-page/' . $orderId . '/complete',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['payPalOrderId' => 'PAYPAL_ORDER_ID', 'orderId' => $orderId]),
        );

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    private function refreshOrder(int $orderId): OrderInterface
    {
        $manager = self::getContainer()->get('sylius.manager.order');
        $manager->clear();

        /** @var OrderInterface $order */
        $order = self::getContainer()->get('sylius.repository.order')->find($orderId);

        return $order;
    }

    private function mockSuccessfulPaymentCompleteProcessor(): void
    {
        self::getContainer()->set('sylius_paypal.processor.payment_complete', new class() implements PaymentCompleteProcessorInterface {
            public function completePayment(PaymentInterface $payment): void
            {
                $payment->setDetails(array_merge($payment->getDetails(), ['status' => StatusAction::STATUS_COMPLETED]));
            }
        });
    }

    private function generateUrl(string $route): string
    {
        /** @var UrlGeneratorInterface $router */
        $router = self::getContainer()->get('router');

        return $router->generate($route, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
