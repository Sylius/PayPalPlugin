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
use Doctrine\DBAL\Connection;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\Model\PayPalWebhookEventStatus;
use Symfony\Component\HttpFoundation\Response;
use Tests\Sylius\PayPalPlugin\Service\DummyRefundPaymentApi;

final class PaymentMethodNotifyWebhookEventsTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    protected function setUp(): void
    {
        parent::setUp();
        DummyRefundPaymentApi::$refundedPaymentIds = [];
    }

    public function test_it_completes_the_payment_once_paypal_says_the_capture_completed(): void
    {
        $order = $this->processingOrder();
        $this->payPalApi()->mockCaptureDetails();
        $this->payPalApi()->mockOrderDetailsWithCapture();

        $this->notifyAboutCapture('PAYMENT.CAPTURE.COMPLETED', 'WH-1');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadPayment($order)->getState());
        self::assertSame(PayPalWebhookEventStatus::Processed->value, $this->webhookEventStatus('WH-1'));
    }

    public function test_a_pending_capture_reaches_its_final_state_from_the_webhooks_alone(): void
    {
        $order = $this->processingOrder();
        $this->payPalApi()->mockCaptureDetails(status: 'PENDING');
        $this->payPalApi()->mockOrderDetailsWithCapture(captureStatus: 'PENDING');
        $this->payPalApi()->mockCaptureDetails();
        $this->payPalApi()->mockOrderDetailsWithCapture();

        $this->notifyAboutCapture('PAYMENT.CAPTURE.PENDING', 'WH-1');
        self::assertSame(PaymentInterface::STATE_PROCESSING, $this->reloadPayment($order)->getState());

        $this->notifyAboutCapture('PAYMENT.CAPTURE.COMPLETED', 'WH-2');
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadPayment($order)->getState());
    }

    public function test_it_fails_the_payment_once_paypal_says_the_capture_was_denied(): void
    {
        $order = $this->processingOrder();
        $this->payPalApi()->mockCaptureDetails(status: 'DECLINED');
        $this->payPalApi()->mockOrderDetailsWithCapture(captureStatus: 'DECLINED');

        $this->notifyAboutCapture('PAYMENT.CAPTURE.DENIED', 'WH-1');

        self::assertSame(PaymentInterface::STATE_FAILED, $this->reloadPayment($order)->getState());
    }

    public function test_it_completes_the_payment_of_a_completed_checkout_order(): void
    {
        $order = $this->processingOrder();
        $this->payPalApi()->mockOrderDetailsWithCapture();

        $this->notify([
            'id' => 'WH-1',
            'event_type' => 'CHECKOUT.ORDER.COMPLETED',
            'resource_type' => 'checkout-order',
            'resource' => ['id' => 'PAYPAL_ORDER_ID', 'status' => 'COMPLETED'],
        ]);

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadPayment($order)->getState());
    }

    public function test_it_refunds_a_completed_payment_paypal_says_was_refunded(): void
    {
        $order = $this->completedOrder();
        $this->payPalApi()->mockRefundDetails();

        $this->notifyAboutRefund('PAYMENT.CAPTURE.REFUNDED', 'WH-1');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        $payment = $this->reloadPayment($order);
        self::assertSame(PaymentInterface::STATE_REFUNDED, $payment->getState());
        self::assertTrue($payment->getDetails()['refunded']);
        self::assertSame('REFUND_ID', $payment->getDetails()['refund_id']);
    }

    public function test_it_refunds_a_completed_payment_paypal_reversed_without_refunding_it_again(): void
    {
        $order = $this->completedOrder();
        $this->payPalApi()->mockRefundDetails();

        $this->notifyAboutRefund('PAYMENT.CAPTURE.REVERSED', 'WH-1');

        self::assertSame(PaymentInterface::STATE_REFUNDED, $this->reloadPayment($order)->getState());
        self::assertSame([], DummyRefundPaymentApi::$refundedPaymentIds);
    }

    public function test_replaying_a_batch_of_events_changes_nothing_again(): void
    {
        $order = $this->processingOrder();
        $this->payPalApi()->mockCaptureDetails();
        $this->payPalApi()->mockOrderDetailsWithCapture();
        $this->payPalApi()->mockRefundDetails();

        $this->notifyAboutCapture('PAYMENT.CAPTURE.COMPLETED', 'WH-1');
        $this->notifyAboutRefund('PAYMENT.CAPTURE.REFUNDED', 'WH-2');
        $this->notifyAboutCapture('PAYMENT.CAPTURE.COMPLETED', 'WH-1');
        $this->notifyAboutRefund('PAYMENT.CAPTURE.REFUNDED', 'WH-2');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_REFUNDED, $this->reloadPayment($order)->getState());
    }

    public function test_it_asks_paypal_to_deliver_an_approval_again_while_the_payer_may_still_be_coming_back(): void
    {
        $paymentRequest = $this->processingCapturePaymentRequest();
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->approvedOrder($paymentRequest, new \DateTimeImmutable()));

        $this->notifyAboutApproval('WH-1');

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        self::assertNull($this->webhookEventStatus('WH-1'));
        self::assertSame(PaymentRequestInterface::STATE_PROCESSING, $this->reloadPaymentRequest($paymentRequest)->getState());
    }

    public function test_it_captures_an_order_the_payer_approved_but_never_came_back_from(): void
    {
        $paymentRequest = $this->processingCapturePaymentRequest();
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->approvedOrder($paymentRequest, new \DateTimeImmutable('-1 hour')));
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID');
        $this->payPalApi()->mockUpdateOrderAddress();
        $this->payPalApi()->mockCapture();
        $this->payPalApi()->mockOrderDetailsWithCapture(value: '0.20');

        $this->notifyAboutApproval('WH-1');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        $reloaded = $this->reloadPaymentRequest($paymentRequest);
        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $reloaded->getState());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $reloaded->getPayment()->getState());
    }

    public function test_it_captures_an_approved_order_again_once_paypal_delivers_the_approval_again_after_a_failed_capture(): void
    {
        $paymentRequest = $this->processingCapturePaymentRequest();
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->approvedOrder($paymentRequest, new \DateTimeImmutable('-1 hour')));
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID');
        $this->payPalApi()->mockUpdateOrderAddress();
        $this->payPalApi()->mockCaptureOfDeclinedInstrument();

        $this->notifyAboutApproval('WH-1');

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $this->client->getResponse()->getStatusCode());
        self::assertNull($this->webhookEventStatus('WH-1'));
        $reloaded = $this->reloadPaymentRequest($paymentRequest);
        self::assertSame(PaymentRequestInterface::STATE_PROCESSING, $reloaded->getState());
        self::assertNotSame(PaymentInterface::STATE_COMPLETED, $reloaded->getPayment()->getState());

        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->approvedOrder($paymentRequest, new \DateTimeImmutable('-1 hour')));
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID');
        $this->payPalApi()->mockUpdateOrderAddress();
        $this->payPalApi()->mockCapture();
        $this->payPalApi()->mockOrderDetailsWithCapture(value: '0.20');

        $this->notifyAboutApproval('WH-1');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PayPalWebhookEventStatus::Processed->value, $this->webhookEventStatus('WH-1'));
        $reloaded = $this->reloadPaymentRequest($paymentRequest);
        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $reloaded->getState());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $reloaded->getPayment()->getState());
    }

    private function processingOrder(): OrderInterface
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/processing_paypal_order.yaml']);

        /** @var OrderInterface $order */
        $order = $fixtures['processing_order'];

        return $order;
    }

    private function completedOrder(): OrderInterface
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/completed_paypal_order.yaml']);

        /** @var OrderInterface $order */
        $order = $fixtures['completed_order'];
        $order->getLastPayment()?->setDetails(['transaction_id' => 'CAPTURE_ID'] + $order->getLastPayment()->getDetails());
        $this->getEntityManager()->flush();

        return $order;
    }

    private function processingCapturePaymentRequest(): PaymentRequestInterface
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml']);

        /** @var PaymentInterface $payment */
        $payment = $fixtures['paypal_payment'];
        /** @var OrderInterface $order */
        $order = $payment->getOrder();
        $order->setShippingAddress($this->shippingAddress());
        $payment->setAmount($order->getTotal());
        $payment->setDetails([
            'status' => 'CAPTURED',
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'reference_id' => 'REFERENCE_ID',
            'payment_amount' => $order->getTotal(),
            'payment_source' => 'paypal',
        ]);

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $gatewayConfig->setUsePayum(false);

        /** @var PaymentRequestInterface $paymentRequest */
        $paymentRequest = self::getContainer()->get('sylius.factory.payment_request')->create($payment, $paymentMethod);
        $paymentRequest->setAction(PaymentRequestInterface::ACTION_CAPTURE);
        $paymentRequest->setState(PaymentRequestInterface::STATE_PROCESSING);

        $this->getEntityManager()->persist($paymentRequest);
        $this->getEntityManager()->flush();

        return $paymentRequest;
    }

    /** @return array<string, mixed> */
    private function approvedOrder(PaymentRequestInterface $paymentRequest, \DateTimeImmutable $approvedAt): array
    {
        return [
            'status' => 'APPROVED',
            'update_time' => $approvedAt->format(\DateTimeInterface::ATOM),
            'purchase_units' => [['custom_id' => (string) $paymentRequest->getId()]],
        ];
    }

    private function notifyAboutCapture(string $eventType, string $eventId): void
    {
        $this->notify([
            'id' => $eventId,
            'event_type' => $eventType,
            'resource_type' => 'capture',
            'resource' => [
                'id' => 'CAPTURE_ID',
                'supplementary_data' => ['related_ids' => ['order_id' => 'PAYPAL_ORDER_ID']],
            ],
        ]);
    }

    private function notifyAboutRefund(string $eventType, string $eventId): void
    {
        $this->notify([
            'id' => $eventId,
            'event_type' => $eventType,
            'resource_type' => 'refund',
            'resource' => [
                'id' => 'REFUND_ID',
                'links' => [['rel' => 'up', 'href' => 'https://api.sandbox.paypal.com/v2/payments/captures/CAPTURE_ID']],
            ],
        ]);
    }

    private function notifyAboutApproval(string $eventId): void
    {
        $this->notify([
            'id' => $eventId,
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource_type' => 'checkout-order',
            'resource' => ['id' => 'PAYPAL_ORDER_ID', 'status' => 'APPROVED'],
        ]);
    }

    /** @param array<string, mixed> $event */
    private function notify(array $event): void
    {
        $this->client->request('POST', '/payment-methods/PAYPAL', [], [], [], (string) json_encode($event));
    }

    private function shippingAddress(): AddressInterface
    {
        /** @var AddressInterface $address */
        $address = self::getContainer()->get('sylius.factory.address')->createNew();
        $address->setFirstName('Oliver');
        $address->setLastName('Queen');
        $address->setStreet('Star City 1');
        $address->setCity('Star City');
        $address->setPostcode('90210');
        $address->setCountryCode('US');

        return $address;
    }

    private function webhookEventStatus(string $eventId): ?string
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $status = $connection->fetchOne(
            'SELECT status FROM sylius_paypal_plugin_webhook_event WHERE event_id = :eventId AND payment_method_code = :paymentMethodCode',
            ['eventId' => $eventId, 'paymentMethodCode' => 'PAYPAL'],
        );

        return false === $status ? null : (string) $status;
    }

    private function reloadPayment(OrderInterface $order): PaymentInterface
    {
        self::getContainer()->get('sylius.manager.order')->clear();

        /** @var OrderInterface $reloaded */
        $reloaded = self::getContainer()->get('sylius.repository.order')->find($order->getId());

        /** @var PaymentInterface $payment */
        $payment = $reloaded->getLastPayment();

        return $payment;
    }

    private function reloadPaymentRequest(PaymentRequestInterface $paymentRequest): PaymentRequestInterface
    {
        $hash = $paymentRequest->getId();
        self::getContainer()->get('sylius.manager.payment_request')->clear();

        /** @var PaymentRequestInterface $reloaded */
        $reloaded = self::getContainer()->get('sylius.repository.payment_request')->find($hash);

        return $reloaded;
    }
}
