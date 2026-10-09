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
use Sylius\PayPalPlugin\Exception\PayPalApiTimeoutException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Sylius\PayPalPlugin\Service\DummyOrderDetailsApi;
use Tests\Sylius\PayPalPlugin\Service\DummyRefundPaymentApi;

final class PayPalWebhookActionTest extends JsonApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DummyOrderDetailsApi::$captureStatus = 'COMPLETED';
        DummyOrderDetailsApi::$failWith = null;
        DummyRefundPaymentApi::$refundedPaymentIds = [];
    }

    public function test_it_completes_the_payment_once_paypal_says_the_capture_completed(): void
    {
        $order = $this->processingOrder();

        $this->sendWebhook('PAYMENT.CAPTURE.COMPLETED');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadPayment($order)->getState());
    }

    public function test_it_changes_nothing_when_the_same_event_is_delivered_again(): void
    {
        $order = $this->processingOrder();

        $this->sendWebhook('PAYMENT.CAPTURE.COMPLETED');
        $this->sendWebhook('PAYMENT.CAPTURE.COMPLETED');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadPayment($order)->getState());
    }

    public function test_it_leaves_the_payment_processing_while_the_capture_is_pending(): void
    {
        DummyOrderDetailsApi::$captureStatus = 'PENDING';
        $order = $this->processingOrder();

        $this->sendWebhook('PAYMENT.CAPTURE.PENDING');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_PROCESSING, $this->reloadPayment($order)->getState());
    }

    public function test_it_fails_the_payment_once_paypal_says_the_capture_was_denied(): void
    {
        DummyOrderDetailsApi::$captureStatus = 'DECLINED';
        $order = $this->processingOrder();

        $this->sendWebhook('PAYMENT.CAPTURE.DENIED');

        self::assertSame(PaymentInterface::STATE_FAILED, $this->reloadPayment($order)->getState());
    }

    public function test_it_keeps_a_capture_completed_for_a_cancelled_payment_to_be_refunded(): void
    {
        $order = $this->processingOrder();
        $order->getLastPayment()?->setState(PaymentInterface::STATE_CANCELLED);
        $this->getEntityManager()->flush();

        $this->sendWebhook('PAYMENT.CAPTURE.COMPLETED');

        $payment = $this->reloadPayment($order);
        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_CANCELLED, $payment->getState());
        self::assertSame(
            ['id' => '123123', 'amount' => 20, 'currency_code' => 'USD'],
            $payment->getDetails()['paypal_late_capture'] ?? null,
        );
    }

    public function test_it_accepts_an_event_it_does_not_handle(): void
    {
        $order = $this->processingOrder();

        $this->sendWebhook('CHECKOUT.ORDER.APPROVED');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_PROCESSING, $this->reloadPayment($order)->getState());
    }

    public function test_it_still_refunds_a_completed_payment(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/completed_paypal_order.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['completed_order'];

        $this->client->request('POST', '/paypal-webhook/api/', [], [], [], json_encode([
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'links' => [
                    ['rel' => 'up', 'href' => 'PAYPAL_ORDER_ID'],
                ],
            ],
        ]));

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_REFUNDED, $this->reloadPayment($order)->getState());
    }

    public function test_it_marks_a_payment_refunded_in_paypal_without_refunding_it_again(): void
    {
        DummyOrderDetailsApi::$captureStatus = 'REFUNDED';
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/completed_paypal_order.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['completed_order'];

        $this->client->request('POST', '/paypal-webhook/api/', [], [], [], json_encode([
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'links' => [
                    ['rel' => 'up', 'href' => 'PAYPAL_ORDER_ID'],
                ],
            ],
        ]));

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_REFUNDED, $this->reloadPayment($order)->getState());
        self::assertSame([], DummyRefundPaymentApi::$refundedPaymentIds);
    }

    public function test_it_asks_paypal_to_deliver_the_event_again_when_settlement_fails(): void
    {
        DummyOrderDetailsApi::$failWith = new PayPalApiTimeoutException();
        $order = $this->processingOrder();

        $this->sendWebhook('PAYMENT.CAPTURE.COMPLETED');

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_PROCESSING, $this->reloadPayment($order)->getState());
    }

    public function test_it_accepts_a_refund_event_it_cannot_read(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/completed_paypal_order.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['completed_order'];

        $this->client->request('POST', '/paypal-webhook/api/', [], [], [], json_encode([
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'links' => [
                    ['rel' => 'self', 'href' => 'https://api-m.paypal.com/v2/payments/refunds/REFUND_ID'],
                ],
            ],
        ]));

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadPayment($order)->getState());
    }

    private function processingOrder(): OrderInterface
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/processing_paypal_order.yaml']);

        /** @var OrderInterface $order */
        $order = $fixtures['processing_order'];

        return $order;
    }

    private function sendWebhook(string $eventType): void
    {
        $this->client->request('POST', '/paypal-webhook/api/', [], [], [], json_encode([
            'event_type' => $eventType,
            'resource' => [
                'id' => 'CAPTURE_ID',
                'supplementary_data' => ['related_ids' => ['order_id' => 'PAYPAL_ORDER_ID']],
            ],
        ]));
    }

    private function reloadPayment(OrderInterface $order): PaymentInterface
    {
        $manager = self::getContainer()->get('sylius.manager.order');
        $manager->clear();

        /** @var OrderInterface $reloaded */
        $reloaded = self::getContainer()->get('sylius.repository.order')->find($order->getId());

        /** @var PaymentInterface $payment */
        $payment = $reloaded->getLastPayment();

        return $payment;
    }
}
