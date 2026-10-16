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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\CommandHandler\NotifyPaymentRequestHandler;
use Sylius\PayPalPlugin\Model\PayPalWebhookEventStatus;
use Symfony\Component\HttpFoundation\Response;

final class PaymentMethodNotifyActionTest extends JsonApiTestCase
{
    public function test_it_records_an_event_of_a_known_payment_it_has_no_processor_for_as_ignored(): void
    {
        $order = $this->processingOrder();

        $this->notify('PAYMENT.CAPTURE.COMPLETED', 'WH-1');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PayPalWebhookEventStatus::Ignored->value, $this->webhookEventStatus('WH-1'));
        self::assertSame(
            [PaymentRequestInterface::STATE_COMPLETED => PayPalWebhookEventStatus::Ignored->value],
            $this->notifyPaymentRequestOutcomes($order),
        );
        self::assertSame(PaymentInterface::STATE_PROCESSING, $this->reloadPayment($order)->getState());
    }

    public function test_it_takes_an_event_delivered_again_for_a_duplicate(): void
    {
        $order = $this->processingOrder();

        $this->notify('PAYMENT.CAPTURE.COMPLETED', 'WH-1');
        $this->notify('PAYMENT.CAPTURE.COMPLETED', 'WH-1');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertEqualsCanonicalizing(
            [PayPalWebhookEventStatus::Ignored->value, NotifyPaymentRequestHandler::WEBHOOK_EVENT_DUPLICATE],
            array_values($this->notifyPaymentRequestOutcomes($order, grouped: false)),
        );
    }

    public function test_it_finds_the_payment_by_the_payment_request_paypal_was_given_as_custom_id(): void
    {
        $order = $this->processingOrder();
        $paymentRequest = $this->capturePaymentRequest($order);

        $this->notify('PAYMENT.CAPTURE.COMPLETED', 'WH-1', resource: [
            'id' => 'UNKNOWN_CAPTURE_ID',
            'custom_id' => (string) $paymentRequest->getId(),
        ]);

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(
            [PaymentRequestInterface::STATE_COMPLETED => PayPalWebhookEventStatus::Ignored->value],
            $this->notifyPaymentRequestOutcomes($order),
        );
    }

    public function test_it_ignores_an_event_that_matches_no_payment(): void
    {
        $order = $this->processingOrder();

        $this->notify('PAYMENT.CAPTURE.COMPLETED', 'WH-1', resource: [
            'id' => 'UNKNOWN_CAPTURE_ID',
            'supplementary_data' => ['related_ids' => ['order_id' => 'UNKNOWN_PAYPAL_ORDER_ID']],
        ]);

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertNull($this->webhookEventStatus('WH-1'));
        self::assertSame([], $this->notifyPaymentRequestOutcomes($order));
    }

    public function test_it_ignores_a_notification_that_carries_no_event(): void
    {
        $this->processingOrder();

        $this->client->request('POST', '/payment-methods/PAYPAL', [], [], [], '{"foo": "bar"}');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
    }

    private function processingOrder(): OrderInterface
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/processing_paypal_order.yaml']);

        /** @var OrderInterface $order */
        $order = $fixtures['processing_order'];

        return $order;
    }

    /** @param array<string, mixed>|null $resource */
    private function notify(string $eventType, string $eventId, ?array $resource = null): void
    {
        $this->client->request('POST', '/payment-methods/PAYPAL', [], [], [], (string) json_encode([
            'id' => $eventId,
            'event_type' => $eventType,
            'resource_type' => 'capture',
            'resource' => $resource ?? [
                'id' => 'CAPTURE_ID',
                'supplementary_data' => ['related_ids' => ['order_id' => 'PAYPAL_ORDER_ID']],
            ],
        ]));
    }

    private function capturePaymentRequest(OrderInterface $order): PaymentRequestInterface
    {
        /** @var PaymentInterface $payment */
        $payment = self::getContainer()->get('sylius.repository.payment')->find($order->getLastPayment()?->getId());

        /** @var PaymentRequestInterface $paymentRequest */
        $paymentRequest = self::getContainer()->get('sylius.factory.payment_request')->create($payment, $payment->getMethod());
        self::getContainer()->get('sylius.repository.payment_request')->add($paymentRequest);

        return $paymentRequest;
    }

    /** @return array<string, string> */
    private function notifyPaymentRequestOutcomes(OrderInterface $order, bool $grouped = true): array
    {
        self::getContainer()->get('sylius.manager.payment_request')->clear();

        /** @var PaymentRequestInterface[] $paymentRequests */
        $paymentRequests = self::getContainer()->get('sylius.repository.payment_request')->findBy([
            'payment' => $order->getLastPayment()?->getId(),
            'action' => PaymentRequestInterface::ACTION_NOTIFY,
        ]);

        $outcomes = [];
        foreach ($paymentRequests as $index => $paymentRequest) {
            $outcome = (string) ($paymentRequest->getResponseData()[NotifyPaymentRequestHandler::RESPONSE_WEBHOOK_EVENT_STATUS] ?? '');
            $outcomes[$grouped ? $paymentRequest->getState() : (string) $index] = $outcome;
        }

        return $outcomes;
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
}
