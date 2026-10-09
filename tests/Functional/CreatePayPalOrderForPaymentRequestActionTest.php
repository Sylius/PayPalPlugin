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
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\Response;

final class CreatePayPalOrderForPaymentRequestActionTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    public function test_it_creates_the_paypal_order_for_the_payment_source_the_payer_picked(): void
    {
        $hash = $this->paymentRequestHash();
        $this->payPalApi()->mockCreateOrder('PAYPAL_ORDER_ID');

        $this->createOrder($hash, 'card');

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame(['hash' => $hash, 'approve_url' => $this->approveUrl($hash), 'paypal_order_id' => 'PAYPAL_ORDER_ID'], $this->responseContent());
        self::assertSame(PaymentRequestInterface::STATE_PROCESSING, $this->paymentRequest($hash)->getState());
    }

    public function test_it_creates_a_paypal_order_for_the_paypal_wallet_when_no_payment_source_is_given(): void
    {
        $hash = $this->paymentRequestHash();
        $this->payPalApi()->mockCreateOrder();

        $this->client->request('POST', sprintf('/en_US/paypal/payment-requests/%s/order', $hash));

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame(['payment_source' => 'paypal'], $this->paymentRequest($hash)->getPayload());
    }

    public function test_it_answers_with_the_same_paypal_order_when_the_payer_picks_the_same_payment_source_again(): void
    {
        $hash = $this->paymentRequestHash();
        $this->payPalApi()->mockCreateOrder('PAYPAL_ORDER_ID');

        $this->createOrder($hash, 'paypal');
        $this->createOrder($hash, 'paypal');

        self::assertSame(['hash' => $hash, 'approve_url' => $this->approveUrl($hash), 'paypal_order_id' => 'PAYPAL_ORDER_ID'], $this->responseContent());
    }

    public function test_it_starts_another_attempt_when_the_payer_switches_to_another_payment_source(): void
    {
        $hash = $this->paymentRequestHash();
        $this->payPalApi()->mockCreateOrder('PAYPAL_ORDER_ID');
        $this->payPalApi()->mockCreateOrder('ANOTHER_PAYPAL_ORDER_ID');

        $this->createOrder($hash, 'paypal');
        $this->createOrder($hash, 'card');

        $content = $this->responseContent();
        self::assertNotSame($hash, $content['hash']);
        self::assertSame('ANOTHER_PAYPAL_ORDER_ID', $content['paypal_order_id']);
        self::assertSame($this->approveUrl($content['hash']), $content['approve_url']);
        self::assertSame(PaymentRequestInterface::STATE_CANCELLED, $this->paymentRequest($hash)->getState());
        self::assertSame(PaymentRequestInterface::STATE_PROCESSING, $this->paymentRequest($content['hash'])->getState());
    }

    public function test_it_sends_the_payer_to_the_bank_and_back_to_the_payment_request(): void
    {
        $hash = $this->paymentRequestHash();
        $this->payPalApi()->mockCreateOrder('PAYPAL_ORDER_ID', [
            'status' => 'PAYER_ACTION_REQUIRED',
            'links' => [['href' => 'https://www.sandbox.paypal.com/payment/trustly?token=PAYPAL_ORDER_ID', 'rel' => 'payer-action', 'method' => 'GET']],
        ]);

        $this->createOrder($hash, 'trustly');

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame('https://www.sandbox.paypal.com/payment/trustly?token=PAYPAL_ORDER_ID', $this->responseContent()['payer_action_url']);
        $details = $this->paymentRequest($hash)->getPayment()->getDetails();
        self::assertArrayNotHasKey('payer_action_return_nonce', $details);
        self::assertSame('https://www.sandbox.paypal.com/payment/trustly?token=PAYPAL_ORDER_ID', $details['payer_action_url']);
    }

    public function test_it_refuses_a_payment_source_the_payment_method_has_not_enabled(): void
    {
        $hash = $this->paymentRequestHash();

        $this->createOrder($hash, 'venmo');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertSame('The requested payment source is not enabled for this payment method.', $this->responseContent()['reason']);
    }

    public function test_it_tells_why_paypal_could_not_be_asked_for_the_order(): void
    {
        $hash = $this->paymentRequestHash();

        $this->createOrder($hash, 'bitcoin');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertSame(['hash' => $hash, 'approve_url' => $this->approveUrl($hash), 'reason' => 'PayPal does not support the requested payment source.'], $this->responseContent());
    }

    public function test_it_refuses_a_payment_that_is_no_longer_waiting_to_be_paid(): void
    {
        $hash = $this->paymentRequestHash(PaymentInterface::STATE_COMPLETED);

        $this->createOrder($hash, 'paypal');

        self::assertSame(Response::HTTP_CONFLICT, $this->client->getResponse()->getStatusCode());
    }

    public function test_it_refuses_a_payment_request_that_is_over(): void
    {
        $hash = $this->paymentRequestHash(paymentRequestState: PaymentRequestInterface::STATE_CANCELLED);

        $this->createOrder($hash, 'paypal');

        self::assertSame(Response::HTTP_CONFLICT, $this->client->getResponse()->getStatusCode());
    }

    public function test_it_knows_no_payment_request_it_cannot_find(): void
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml']);

        $this->createOrder('00000000-0000-0000-0000-000000000000', 'paypal');

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function test_it_knows_no_payment_request_under_something_that_is_not_a_hash(): void
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml']);

        $this->createOrder('not-a-hash', 'paypal');

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    private function paymentRequestHash(
        string $paymentState = PaymentInterface::STATE_NEW,
        string $paymentRequestState = PaymentRequestInterface::STATE_NEW,
    ): string {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml']);

        /** @var PaymentInterface $payment */
        $payment = $fixtures['paypal_payment'];
        $payment->setState($paymentState);
        $payment->getOrder()?->setBillingAddress($this->billingAddress());

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $gatewayConfig->setUsePayum(false);
        $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), ['trustly_enabled' => true]));

        $paymentRequest = self::getContainer()->get('sylius.factory.payment_request')->create($payment, $paymentMethod);
        $paymentRequest->setAction(PaymentRequestInterface::ACTION_CAPTURE);
        $paymentRequest->setState($paymentRequestState);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($paymentRequest);
        $entityManager->flush();

        return (string) $paymentRequest->getId();
    }

    private function createOrder(string $hash, string $paymentSource): void
    {
        $this->client->request(
            'POST',
            sprintf('/en_US/paypal/payment-requests/%s/order', $hash),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['paymentSource' => $paymentSource], \JSON_THROW_ON_ERROR),
        );
    }

    /** @return array<string, mixed> */
    private function responseContent(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function paymentRequest(string $hash): PaymentRequestInterface
    {
        $this->getEntityManager()->clear();

        /** @var PaymentRequestInterface $paymentRequest */
        $paymentRequest = self::getContainer()->get('sylius.repository.payment_request')->find($hash);

        return $paymentRequest;
    }

    private function approveUrl(string $hash): string
    {
        return sprintf('/en_US/payment-request/pay/%s', $hash);
    }

    private function billingAddress(): AddressInterface
    {
        /** @var AddressInterface $address */
        $address = self::getContainer()->get('sylius.factory.address')->createNew();
        $address->setFirstName('Patrick');
        $address->setLastName('Watson');
        $address->setStreet('Damrak 1');
        $address->setCity('Amsterdam');
        $address->setPostcode('1012 LG');
        $address->setCountryCode('NL');

        return $address;
    }
}
