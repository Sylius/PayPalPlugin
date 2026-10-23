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

    public function test_it_completes_an_authorized_payment_once_paypal_says_its_authorization_was_captured(): void
    {
        $order = $this->authorizedOrder();
        $this->payPalApi()->mockCaptureDetails();
        $this->payPalApi()->mockOrderDetailsWithCapturedAuthorization();

        $this->notifyAboutCapture('PAYMENT.CAPTURE.COMPLETED', 'WH-1');

        $payment = $this->reloadPayment($order);
        self::assertSame(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertSame('CAPTURED', $payment->getDetails()['authorization_status']);
        self::assertSame('CAPTURE_ID', $payment->getDetails()['transaction_id']);
        self::assertArrayNotHasKey('paypal_late_capture', $payment->getDetails());
    }

    public function test_it_completes_the_capture_request_the_admin_is_waiting_for_once_paypal_completes_the_capture(): void
    {
        $order = $this->authorizedOrder();
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment();
        $paymentRequest = self::getContainer()->get('sylius.factory.payment_request')->create($payment, $payment->getMethod());
        $paymentRequest->setAction('capture_authorization');
        $paymentRequest->setState(PaymentRequestInterface::STATE_PROCESSING);
        $this->getEntityManager()->persist($paymentRequest);
        $this->getEntityManager()->flush();
        $this->payPalApi()->mockCaptureDetails();
        $this->payPalApi()->mockOrderDetailsWithCapturedAuthorization();

        $this->notifyAboutCapture('PAYMENT.CAPTURE.COMPLETED', 'WH-1');

        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadPayment($order)->getState());
        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $this->reloadPaymentRequest($paymentRequest)->getState());
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

    public function test_it_completes_a_payment_whose_payer_never_came_back_from_the_bank(): void
    {
        $order = $this->processingOrder();
        $order->getLastPayment()?->setState(PaymentInterface::STATE_NEW);
        $this->getEntityManager()->flush();
        $this->payPalApi()->mockCaptureDetails();
        $this->payPalApi()->mockOrderDetailsWithCapture();

        $this->notifyAboutCapture('PAYMENT.CAPTURE.COMPLETED', 'WH-1');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadPayment($order)->getState());
    }

    public function test_it_keeps_a_capture_completed_for_a_cancelled_payment_to_be_refunded(): void
    {
        $order = $this->processingOrder();
        $order->getLastPayment()?->setState(PaymentInterface::STATE_CANCELLED);
        $this->getEntityManager()->flush();
        $this->payPalApi()->mockCaptureDetails();
        $this->payPalApi()->mockOrderDetailsWithCapture();

        $this->notifyAboutCapture('PAYMENT.CAPTURE.COMPLETED', 'WH-1');

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
        $this->payPalApi()->mockCaptureDetails();
        $this->payPalApi()->mockOrderDetailsWithCapture();

        $this->notifyAboutCapture('PAYMENT.CAPTURE.COMPLETED', 'WH-1');

        $payment = $this->reloadPayment($order);
        self::assertSame(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertSame('PAYPAL_ORDER_ID', $payment->getDetails()['paypal_order_id']);
        self::assertSame('trustly', $payment->getDetails()['payment_source']);
        self::assertSame(['ANOTHER_PAYPAL_ORDER_ID' => 'paypal'], $payment->getDetails()['superseded_paypal_orders']);
    }

    public function test_it_keeps_the_capture_of_a_superseded_bank_order_on_a_paid_payment_to_be_refunded(): void
    {
        $order = $this->orderPaidAfterAnotherAttempt(PaymentInterface::STATE_COMPLETED);
        $this->payPalApi()->mockCaptureDetails();
        $this->payPalApi()->mockOrderDetailsWithCapture();

        $this->notifyAboutCapture('PAYMENT.CAPTURE.COMPLETED', 'WH-1');

        $payment = $this->reloadPayment($order);
        self::assertSame(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertSame('ANOTHER_PAYPAL_ORDER_ID', $payment->getDetails()['paypal_order_id']);
        self::assertEquals(
            ['id' => 'CAPTURE_ID', 'order_id' => 'PAYPAL_ORDER_ID', 'amount' => 20, 'currency_code' => 'USD'],
            $payment->getDetails()['paypal_late_capture'] ?? null,
        );
    }

    public function test_it_asks_paypal_to_deliver_the_event_again_when_settlement_fails(): void
    {
        $order = $this->processingOrder();
        $this->payPalApi()->mockCaptureDetails();
        $this->payPalApi()->mockOrderDetailsUnreachable();

        $this->notifyAboutCapture('PAYMENT.CAPTURE.COMPLETED', 'WH-1');

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_PROCESSING, $this->reloadPayment($order)->getState());
        self::assertNull($this->webhookEventStatus('WH-1'));
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

    public function test_it_stores_the_refund_paypal_confirmed_and_refunds_the_payment_without_calling_paypal(): void
    {
        $order = $this->completedOrder();
        $this->payPalApi()->mockRefundDetails();

        $this->notifyAboutRefund('PAYMENT.CAPTURE.REFUNDED', 'WH-1');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        $payment = $this->reloadPayment($order);
        self::assertSame(PaymentInterface::STATE_REFUNDED, $payment->getState());
        self::assertTrue($payment->getDetails()['refunded']);
        self::assertSame('REFUND_ID', $payment->getDetails()['refund_id']);
        self::assertSame([], DummyRefundPaymentApi::$refundedPaymentIds);
    }

    public function test_it_refunds_a_completed_payment_paypal_reversed_without_refunding_it_again(): void
    {
        $order = $this->completedOrder();
        $this->payPalApi()->mockRefundDetails();

        $this->notifyAboutRefund('PAYMENT.CAPTURE.REVERSED', 'WH-1');

        self::assertSame(PaymentInterface::STATE_REFUNDED, $this->reloadPayment($order)->getState());
        self::assertSame([], DummyRefundPaymentApi::$refundedPaymentIds);
    }

    public function test_it_completes_the_pending_refund_of_a_late_capture_once_paypal_says_it_was_refunded(): void
    {
        $order = $this->completedOrder();
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment();
        $payment->setState(PaymentInterface::STATE_CANCELLED);
        $payment->setDetails([
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'paypal_late_capture' => ['id' => 'CAPTURE_ID', 'amount' => 20, 'currency_code' => 'USD'],
        ]);
        /** @var PaymentRequestInterface $paymentRequest */
        $paymentRequest = self::getContainer()->get('sylius.factory.payment_request')->create($payment, $payment->getMethod());
        $paymentRequest->setAction('refund_late_capture');
        $paymentRequest->setState(PaymentRequestInterface::STATE_PROCESSING);
        $paymentRequest->setResponseData(['refund_id' => 'REFUND_ID', 'refund_status' => 'PENDING']);
        $this->getEntityManager()->persist($paymentRequest);
        $this->getEntityManager()->flush();
        $this->payPalApi()->mockRefundDetails();

        $this->notifyAboutRefund('PAYMENT.CAPTURE.REFUNDED', 'WH-1');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $this->reloadPaymentRequest($paymentRequest)->getState());
        $payment = $this->reloadPayment($order);
        self::assertSame(PaymentInterface::STATE_CANCELLED, $payment->getState());
        self::assertTrue($payment->getDetails()['paypal_late_capture']['refunded']);
        self::assertSame('REFUND_ID', $payment->getDetails()['paypal_late_capture']['refund_id']);
    }

    public function test_a_reversal_refunds_the_payment_but_leaves_the_refund_the_admin_is_waiting_for_alone(): void
    {
        $order = $this->completedOrder();
        $paymentRequest = $this->pendingRefundPaymentRequest($order, 'ADMIN_REFUND_ID');
        $this->payPalApi()->mockRefundDetails();

        $this->notifyAboutRefund('PAYMENT.CAPTURE.REVERSED', 'WH-1');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        $payment = $this->reloadPayment($order);
        self::assertSame(PaymentInterface::STATE_REFUNDED, $payment->getState());
        self::assertNull($payment->getDetails()['refund_id']);
        self::assertSame(PaymentRequestInterface::STATE_PROCESSING, $this->reloadPaymentRequest($paymentRequest)->getState());
    }

    public function test_a_refund_made_outside_the_shop_leaves_the_refund_the_admin_is_waiting_for_alone(): void
    {
        $order = $this->completedOrder();
        $paymentRequest = $this->pendingRefundPaymentRequest($order, 'ADMIN_REFUND_ID');
        $this->payPalApi()->mockRefundDetails();

        $this->notifyAboutRefund('PAYMENT.CAPTURE.REFUNDED', 'WH-1');

        self::assertSame(PaymentInterface::STATE_REFUNDED, $this->reloadPayment($order)->getState());
        self::assertSame(PaymentRequestInterface::STATE_PROCESSING, $this->reloadPaymentRequest($paymentRequest)->getState());
    }

    public function test_it_completes_the_refund_request_waiting_for_paypal(): void
    {
        $order = $this->completedOrder();
        $paymentRequest = $this->pendingRefundPaymentRequest($order, 'REFUND_ID');
        $this->payPalApi()->mockRefundDetails();

        $this->notifyAboutRefund('PAYMENT.CAPTURE.REFUNDED', 'WH-1');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_REFUNDED, $this->reloadPayment($order)->getState());
        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $this->reloadPaymentRequest($paymentRequest)->getState());
    }

    public function test_it_accepts_a_refund_event_whose_refund_does_not_point_at_its_capture(): void
    {
        $order = $this->completedOrder();
        $this->payPalHttpClient()->addExpectation('GET', 'v2/payments/refunds/REFUND_ID', [
            'id' => 'REFUND_ID',
            'links' => [['rel' => 'self', 'href' => 'https://api.sandbox.paypal.com/v2/payments/refunds/REFUND_ID']],
        ]);

        $this->notifyAboutRefund('PAYMENT.CAPTURE.REFUNDED', 'WH-1');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadPayment($order)->getState());
        self::assertSame(PayPalWebhookEventStatus::Failed->value, $this->webhookEventStatus('WH-1'));
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

    public function test_it_captures_the_order_of_an_approval_right_away(): void
    {
        $paymentRequest = $this->processingCapturePaymentRequest();
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->approvedOrder($paymentRequest));
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

    public function test_it_authorizes_the_order_of_an_approval_when_the_payment_method_authorizes(): void
    {
        $paymentRequest = $this->processingCapturePaymentRequest(PaymentRequestInterface::ACTION_AUTHORIZE);
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->approvedOrder($paymentRequest));
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID');
        $this->payPalApi()->mockUpdateOrderAddress();
        $this->payPalApi()->mockAuthorize();
        $this->payPalApi()->mockOrderDetailsWithAuthorization();

        $this->notifyAboutApproval('WH-1');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        $reloaded = $this->reloadPaymentRequest($paymentRequest);
        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $reloaded->getState());
        self::assertSame(PaymentInterface::STATE_AUTHORIZED, $reloaded->getPayment()->getState());
    }

    public function test_it_keeps_a_capture_paypal_refused_on_the_failed_payment_request_and_accepts_the_approval(): void
    {
        $paymentRequest = $this->processingCapturePaymentRequest();
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->approvedOrder($paymentRequest));
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID');
        $this->payPalApi()->mockUpdateOrderAddress();
        $this->payPalApi()->mockCaptureOfDeclinedInstrument();

        $this->notifyAboutApproval('WH-1');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PayPalWebhookEventStatus::Processed->value, $this->webhookEventStatus('WH-1'));
        $reloaded = $this->reloadPaymentRequest($paymentRequest);
        self::assertSame(PaymentRequestInterface::STATE_FAILED, $reloaded->getState());
        self::assertSame(['reason' => 'PayPal declined the payment method. Choose another one.'], $reloaded->getResponseData());
        self::assertNotSame(PaymentInterface::STATE_COMPLETED, $reloaded->getPayment()->getState());
    }

    public function test_it_leaves_an_order_the_buyer_has_already_captured_alone(): void
    {
        $paymentRequest = $this->processingCapturePaymentRequest();
        $paymentRequest->setState(PaymentRequestInterface::STATE_COMPLETED);
        $this->getEntityManager()->flush();
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->approvedOrder($paymentRequest));

        $this->notifyAboutApproval('WH-1');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $this->reloadPaymentRequest($paymentRequest)->getState());
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

    private function authorizedOrder(): OrderInterface
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/shipping.yaml', 'resources/authorized_paypal_order.yaml']);

        /** @var OrderInterface $order */
        $order = $fixtures['authorized_order'];

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

    private function pendingRefundPaymentRequest(OrderInterface $order, string $refundId): PaymentRequestInterface
    {
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment();
        /** @var PaymentRequestInterface $paymentRequest */
        $paymentRequest = self::getContainer()->get('sylius.factory.payment_request')->create($payment, $payment->getMethod());
        $paymentRequest->setAction(PaymentRequestInterface::ACTION_REFUND);
        $paymentRequest->setState(PaymentRequestInterface::STATE_PROCESSING);
        $paymentRequest->setResponseData(['refund_id' => $refundId, 'refund_status' => 'PENDING']);
        $this->getEntityManager()->persist($paymentRequest);
        $this->getEntityManager()->flush();

        return $paymentRequest;
    }

    private function processingCapturePaymentRequest(string $action = PaymentRequestInterface::ACTION_CAPTURE): PaymentRequestInterface
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
        $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), ['use_authorize' => PaymentRequestInterface::ACTION_AUTHORIZE === $action]));

        /** @var PaymentRequestInterface $paymentRequest */
        $paymentRequest = self::getContainer()->get('sylius.factory.payment_request')->create($payment, $paymentMethod);
        $paymentRequest->setAction($action);
        $paymentRequest->setState(PaymentRequestInterface::STATE_PROCESSING);

        $this->getEntityManager()->persist($paymentRequest);
        $this->getEntityManager()->flush();

        return $paymentRequest;
    }

    /** @return array<string, mixed> */
    private function approvedOrder(PaymentRequestInterface $paymentRequest): array
    {
        return [
            'status' => 'APPROVED',
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
