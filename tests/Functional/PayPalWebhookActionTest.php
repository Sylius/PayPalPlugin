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
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Symfony\Component\HttpFoundation\Response;
use Tests\Sylius\PayPalPlugin\Service\DummyRefundPaymentApi;

final class PayPalWebhookActionTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    protected function setUp(): void
    {
        parent::setUp();
        DummyRefundPaymentApi::reset();
    }

    public function test_it_completes_the_payment_once_paypal_says_the_capture_completed(): void
    {
        $order = $this->processingOrder();
        $this->payPalApi()->mockOrderDetailsWithCapture();

        $this->sendWebhook('PAYMENT.CAPTURE.COMPLETED');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadPayment($order)->getState());
    }

    public function test_it_completes_a_payment_whose_payer_never_came_back_from_the_bank(): void
    {
        $order = $this->processingOrder();
        $payment = $order->getLastPayment();
        $payment?->setState(PaymentInterface::STATE_NEW);
        $this->getEntityManager()->flush();
        $this->payPalApi()->mockOrderDetailsWithCapture();

        $this->sendWebhook('PAYMENT.CAPTURE.COMPLETED');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadPayment($order)->getState());
    }

    public function test_it_changes_nothing_when_the_same_event_is_delivered_again(): void
    {
        $order = $this->processingOrder();
        $this->payPalApi()->mockOrderDetailsWithCapture();
        $this->payPalApi()->mockOrderDetailsWithCapture();

        $this->sendWebhook('PAYMENT.CAPTURE.COMPLETED');
        $this->sendWebhook('PAYMENT.CAPTURE.COMPLETED');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadPayment($order)->getState());
    }

    public function test_it_leaves_the_payment_processing_while_the_capture_is_pending(): void
    {
        $order = $this->processingOrder();
        $this->payPalApi()->mockOrderDetailsWithCapture(captureStatus: 'PENDING');

        $this->sendWebhook('PAYMENT.CAPTURE.PENDING');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_PROCESSING, $this->reloadPayment($order)->getState());
    }

    public function test_it_fails_the_payment_once_paypal_says_the_capture_was_denied(): void
    {
        $order = $this->processingOrder();
        $this->payPalApi()->mockOrderDetailsWithCapture(captureStatus: 'DECLINED');

        $this->sendWebhook('PAYMENT.CAPTURE.DENIED');

        self::assertSame(PaymentInterface::STATE_FAILED, $this->reloadPayment($order)->getState());
    }

    public function test_it_keeps_a_capture_completed_for_a_cancelled_payment_to_be_refunded(): void
    {
        $order = $this->processingOrder();
        $order->getLastPayment()?->setState(PaymentInterface::STATE_CANCELLED);
        $this->getEntityManager()->flush();
        $this->payPalApi()->mockOrderDetailsWithCapture();

        $this->sendWebhook('PAYMENT.CAPTURE.COMPLETED');

        $payment = $this->reloadPayment($order);
        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_CANCELLED, $payment->getState());
        self::assertEquals(
            ['id' => 'CAPTURE_ID', 'order_id' => 'PAYPAL_ORDER_ID', 'amount' => 20, 'currency_code' => 'USD'],
            $payment->getDetails()['paypal_late_capture'] ?? null,
        );
    }

    public function test_it_pays_the_payment_with_the_bank_order_another_attempt_superseded(): void
    {
        $order = $this->orderPaidAfterAnotherAttempt(PaymentInterface::STATE_NEW);
        $this->payPalApi()->mockOrderDetailsWithCapture();

        $this->sendWebhook('PAYMENT.CAPTURE.COMPLETED');

        $payment = $this->reloadPayment($order);
        self::assertSame(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertSame('PAYPAL_ORDER_ID', $payment->getDetails()['paypal_order_id']);
        self::assertSame('trustly', $payment->getDetails()['payment_source']);
        self::assertSame(['ANOTHER_PAYPAL_ORDER_ID' => 'paypal'], $payment->getDetails()['superseded_paypal_orders']);
    }

    public function test_it_keeps_the_capture_of_a_superseded_bank_order_on_a_paid_payment_to_be_refunded(): void
    {
        $order = $this->orderPaidAfterAnotherAttempt(PaymentInterface::STATE_COMPLETED);
        $this->payPalApi()->mockOrderDetailsWithCapture();

        $this->sendWebhook('PAYMENT.CAPTURE.COMPLETED');

        $payment = $this->reloadPayment($order);
        self::assertSame(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertSame('ANOTHER_PAYPAL_ORDER_ID', $payment->getDetails()['paypal_order_id']);
        self::assertEquals(
            ['id' => 'CAPTURE_ID', 'order_id' => 'PAYPAL_ORDER_ID', 'amount' => 20, 'currency_code' => 'USD'],
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

    public function test_it_stores_the_refund_paypal_confirmed_and_refunds_the_payment_without_calling_paypal(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/completed_paypal_order.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['completed_order'];

        $this->sendRefundWebhook();

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        $payment = $this->reloadPayment($order);
        self::assertSame(PaymentInterface::STATE_REFUNDED, $payment->getState());
        self::assertTrue(PayPalPaymentDetails::fromPayment($payment)->isRefunded());
        self::assertSame('REFUND_ID', PayPalPaymentDetails::fromPayment($payment)->refundId());
        self::assertSame([], DummyRefundPaymentApi::$refundedPaymentIds);
    }

    public function test_it_completes_the_refund_request_waiting_for_paypal(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/completed_paypal_order.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['completed_order'];
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment();
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var PaymentRequestInterface $paymentRequest */
        $paymentRequest = self::getContainer()->get('sylius.factory.payment_request')->create($payment, $paymentMethod);
        $paymentRequest->setAction(PaymentRequestInterface::ACTION_REFUND);
        $paymentRequest->setState(PaymentRequestInterface::STATE_PROCESSING);
        $this->getEntityManager()->persist($paymentRequest);
        $this->getEntityManager()->flush();
        $hash = $paymentRequest->getId();

        $this->sendRefundWebhook();

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_REFUNDED, $this->reloadPayment($order)->getState());
        /** @var PaymentRequestInterface $reloadedPaymentRequest */
        $reloadedPaymentRequest = self::getContainer()->get('sylius.repository.payment_request')->find($hash);
        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $reloadedPaymentRequest->getState());
    }

    public function test_it_asks_paypal_to_deliver_the_event_again_when_settlement_fails(): void
    {
        $order = $this->processingOrder();
        $this->payPalApi()->mockOrderDetailsUnreachable();

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

    private function orderPaidAfterAnotherAttempt(string $paymentState): OrderInterface
    {
        $order = $this->processingOrder();
        $payment = $order->getLastPayment();
        $payment?->setState($paymentState);
        $payment?->setDetails([
            'status' => 'CAPTURED',
            'paypal_order_id' => 'ANOTHER_PAYPAL_ORDER_ID',
            'payment_source' => 'paypal',
            'superseded_paypal_orders' => ['PAYPAL_ORDER_ID' => 'trustly'],
        ]);
        $this->getEntityManager()->flush();

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

    private function sendRefundWebhook(): void
    {
        $this->client->request('POST', '/paypal-webhook/api/', [], [], [], json_encode([
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'id' => 'REFUND_ID',
                'status' => 'COMPLETED',
                'links' => [
                    ['rel' => 'up', 'href' => 'PAYPAL_ORDER_ID'],
                ],
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
