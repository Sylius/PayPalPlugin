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
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Sylius\PayPalPlugin\Service\DummyRefundPaymentApi;

final class ShopApiPaymentRequestEligibilityTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    protected function tearDown(): void
    {
        DummyRefundPaymentApi::reset();

        parent::tearDown();
    }

    public function test_it_creates_the_paypal_order_for_an_enabled_payment_source(): void
    {
        $payment = $this->loadNewOrderPayment();
        $this->payPalApi()->mockCreateOrder();

        $this->postPaymentRequest($payment, ['payload' => ['payment_source' => 'paypal']]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $content = $this->responseContent();
        self::assertSame(PaymentRequestInterface::STATE_PROCESSING, $content['state']);
        self::assertSame(['paypal_order_id' => 'PAYPAL_ORDER_ID'], $content['responseData']);
    }

    public function test_it_refuses_a_refund_requested_by_the_customer(): void
    {
        DummyRefundPaymentApi::reset();
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/shipping.yaml', 'resources/shippable_paypal_order.yaml']);

        $this->postPaymentRequest($fixtures['shippable_paypal_payment'], ['action' => 'refund'], 'SHIPPABLE_TOKEN');

        $this->assertViolation('action', 'The "refund" action cannot be requested for a PayPal payment.');
        self::assertSame([], DummyRefundPaymentApi::$refundedPaymentIds);
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadPayment($fixtures['shippable_paypal_payment'])->getState());
    }

    public function test_it_accepts_a_status_request_without_a_payload(): void
    {
        $payment = $this->loadNewOrderPayment();

        $this->postPaymentRequest($payment, ['action' => 'status']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('status', $this->responseContent()['action']);
    }

    public function test_it_refuses_the_authorize_action_for_a_payment_method_that_captures_payments(): void
    {
        $payment = $this->loadNewOrderPayment();

        $this->postPaymentRequest($payment, ['action' => 'authorize', 'payload' => ['payment_source' => 'paypal']]);

        $this->assertViolation('action', 'This PayPal payment method takes payments with the "capture" action, not "authorize".');
        self::assertSame([], $this->paymentRequestsOf($payment));
    }

    public function test_it_refuses_the_capture_action_for_a_payment_method_that_authorizes_payments(): void
    {
        $payment = $this->loadNewOrderPayment();
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), ['use_authorize' => true]));
        $this->getEntityManager()->flush();

        $this->postPaymentRequest($payment, ['action' => 'capture', 'payload' => ['payment_source' => 'paypal']]);

        $this->assertViolation('action', 'This PayPal payment method takes payments with the "authorize" action, not "capture".');
        self::assertSame([], $this->paymentRequestsOf($payment));
    }

    public function test_it_refuses_a_notification_requested_by_the_customer(): void
    {
        $payment = $this->loadNewOrderPayment();

        $this->postPaymentRequest($payment, ['action' => 'notify', 'payload' => ['payment_source' => 'paypal']]);

        $this->assertViolation('action', 'The "notify" action cannot be requested for a PayPal payment.');
        self::assertSame([], $this->paymentRequestsOf($payment));
    }

    public function test_it_refuses_a_payment_source_paypal_does_not_support(): void
    {
        $payment = $this->loadNewOrderPayment();

        $this->postPaymentRequest($payment, ['payload' => ['payment_source' => 'bitcoin']]);

        $this->assertViolation('payload.payment_source', 'The "bitcoin" payment source is not available for this payment method.');
        self::assertSame([], $this->paymentRequestsOf($payment));
    }

    public function test_it_refuses_a_payment_source_that_is_not_enabled(): void
    {
        $payment = $this->loadNewOrderPayment();

        $this->postPaymentRequest($payment, ['payload' => ['payment_source' => 'venmo']]);

        $this->assertViolation('payload.payment_source', 'The "venmo" payment source is not available for this payment method.');
    }

    public function test_it_refuses_a_payment_request_without_the_payment_source(): void
    {
        $payment = $this->loadNewOrderPayment();

        $this->postPaymentRequest($payment, []);

        $this->assertViolation('payload', 'The payload must contain only the "payment_source" to pay with.');
    }

    public function test_it_refuses_a_payload_with_anything_but_the_payment_source(): void
    {
        $payment = $this->loadNewOrderPayment();

        $this->postPaymentRequest($payment, ['payload' => [
            'payment_source' => 'paypal',
            'return_url' => 'https://attacker.example/return',
        ]]);

        $this->assertViolation('payload', 'The payload must contain only the "payment_source" to pay with.');
    }

    public function test_it_refuses_a_paypal_payment_method_of_another_channel(): void
    {
        $payment = $this->loadNewOrderPayment();
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var ChannelInterface $channel */
        $channel = $payment->getOrder()->getChannel();
        $paymentMethod->removeChannel($channel);
        $this->getEntityManager()->flush();

        $this->postPaymentRequest($payment, ['payload' => ['payment_source' => 'paypal']]);

        $this->assertViolation('paymentMethodCode', 'This PayPal payment method is not available in the channel of the order.');
    }

    public function test_it_refuses_to_start_a_new_payment_request_with_an_unavailable_payment_source(): void
    {
        $payment = $this->loadNewOrderPayment();
        $paymentRequest = $this->createPaymentRequest($payment);

        $this->putPaymentRequest($paymentRequest, ['payment_source' => 'venmo']);

        $this->assertViolation('payload.payment_source', 'The "venmo" payment source is not available for this payment method.');
    }

    public function test_it_starts_a_new_payment_request_with_an_enabled_payment_source(): void
    {
        $payment = $this->loadNewOrderPayment();
        $paymentRequest = $this->createPaymentRequest($payment);
        $this->payPalApi()->mockCreateOrder();

        $this->putPaymentRequest($paymentRequest, ['payment_source' => 'paypal']);

        self::assertResponseIsSuccessful();
        self::assertSame(PaymentRequestInterface::STATE_PROCESSING, $this->responseContent()['state']);
    }

    private function loadNewOrderPayment(): PaymentInterface
    {
        return $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml'])['paypal_payment'];
    }

    private function createPaymentRequest(PaymentInterface $payment): PaymentRequestInterface
    {
        $paymentRequest = self::getContainer()->get('sylius.factory.payment_request')->create($payment, $payment->getMethod());
        $paymentRequest->setAction(PaymentRequestInterface::ACTION_CAPTURE);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($paymentRequest);
        $entityManager->flush();

        return $paymentRequest;
    }

    /** @param array<string, mixed> $body */
    private function postPaymentRequest(PaymentInterface $payment, array $body, string $orderToken = 'TOKEN'): void
    {
        $this->client->request(
            'POST',
            sprintf('/api/v2/shop/orders/%s/payment-requests', $orderToken),
            server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
            content: json_encode(array_merge(['paymentId' => $payment->getId(), 'paymentMethodCode' => 'PAYPAL'], $body), \JSON_THROW_ON_ERROR),
        );
    }

    /** @param array<string, mixed> $payload */
    private function putPaymentRequest(PaymentRequestInterface $paymentRequest, array $payload): void
    {
        $this->client->request(
            'PUT',
            sprintf('/api/v2/shop/payment-requests/%s', $paymentRequest->getId()),
            server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
            content: json_encode(['payload' => $payload], \JSON_THROW_ON_ERROR),
        );
    }

    private function assertViolation(string $propertyPath, string $message): void
    {
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertContains(
            ['propertyPath' => $propertyPath, 'message' => $message],
            array_map(
                static fn (array $violation): array => ['propertyPath' => $violation['propertyPath'], 'message' => $violation['message']],
                $this->responseContent()['violations'],
            ),
        );
    }

    /** @return array<string, mixed> */
    private function responseContent(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /** @return list<PaymentRequestInterface> */
    private function paymentRequestsOf(PaymentInterface $payment): array
    {
        return self::getContainer()->get('sylius.repository.payment_request')->findBy(['payment' => $payment]);
    }

    private function reloadPayment(PaymentInterface $payment): PaymentInterface
    {
        $this->getEntityManager()->clear();

        return self::getContainer()->get('sylius.repository.payment')->find($payment->getId());
    }
}
