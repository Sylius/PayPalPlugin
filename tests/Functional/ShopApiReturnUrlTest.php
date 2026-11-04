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
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

final class ShopApiReturnUrlTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    private const PAYER_ACTION_URL = 'https://www.sandbox.paypal.com/payment/trustly?token=PAYPAL_ORDER_ID';

    public function test_it_sends_the_payer_back_to_the_headless_storefront_after_the_bank(): void
    {
        $payment = $this->loadPayment();
        $this->payPalApi()->mockCreateOrder('PAYPAL_ORDER_ID', [
            'status' => 'PAYER_ACTION_REQUIRED',
            'links' => [['href' => self::PAYER_ACTION_URL, 'rel' => 'payer-action', 'method' => 'GET']],
        ]);

        $this->client->request(
            'POST',
            '/api/v2/shop/orders/TOKEN/payment-requests',
            server: ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
            content: json_encode([
                'paymentId' => $payment->getId(),
                'paymentMethodCode' => 'PAYPAL',
                'payload' => ['payment_source' => 'trustly'],
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $content = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(PaymentRequestInterface::STATE_PROCESSING, $content['state']);
        self::assertSame(self::PAYER_ACTION_URL, $content['responseData']['payer_action_url']);

        $this->assertPayerReturnsToTheHeadlessStorefront($content['hash']);
    }

    public function test_it_sends_the_payer_back_to_the_headless_storefront_when_a_worker_creates_the_paypal_order(): void
    {
        $payment = $this->loadPayment();
        $this->payPalApi()->mockCreateOrder('PAYPAL_ORDER_ID', [
            'status' => 'PAYER_ACTION_REQUIRED',
            'links' => [['href' => self::PAYER_ACTION_URL, 'rel' => 'payer-action', 'method' => 'GET']],
        ]);
        $paymentRequest = $this->createPaymentRequest($payment);

        /** @var RequestStack $requestStack */
        $requestStack = $this->get('request_stack');
        $requestStack->push(Request::create('/api/v2/shop/orders/TOKEN/payment-requests', Request::METHOD_POST));
        $command = $this->get('sylius.command_provider.payment_request.default')->provide($paymentRequest);
        $requestStack->pop();

        $serializer = new PhpSerializer();
        $envelope = $serializer->decode($serializer->encode(new Envelope($command)));
        $this->get('sylius.payment_request.command_bus')->dispatch($envelope->with(new ReceivedStamp('payment_request')));

        $this->assertPayerReturnsToTheHeadlessStorefront((string) $paymentRequest->getId());
    }

    private function assertPayerReturnsToTheHeadlessStorefront(string $hash): void
    {
        $returnUrl = sprintf('https://headless.example.com/paypal/return/%s', $hash);
        $experienceContext = $this->createOrderRequestBody()['payment_source']['trustly']['experience_context'];
        self::assertSame($returnUrl, $experienceContext['return_url']);
        self::assertSame($returnUrl . '?payer_cancelled=1', $experienceContext['cancel_url']);
    }

    private function createPaymentRequest(PaymentInterface $payment): PaymentRequestInterface
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var PaymentRequestInterface $paymentRequest */
        $paymentRequest = $this->get('sylius.factory.payment_request')->create($payment, $paymentMethod);
        $paymentRequest->setAction(PaymentRequestInterface::ACTION_CAPTURE);
        $paymentRequest->setPayload(['payment_source' => 'trustly']);
        $this->getEntityManager()->persist($paymentRequest);
        $this->getEntityManager()->flush();

        return $paymentRequest;
    }

    private function loadPayment(): PaymentInterface
    {
        /** @var PaymentInterface $payment */
        $payment = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml'])['paypal_payment'];
        $payment->getOrder()?->setBillingAddress($this->billingAddress());

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), ['trustly_enabled' => true]));

        $this->getEntityManager()->flush();

        return $payment;
    }

    /** @return array<string, mixed> */
    private function createOrderRequestBody(): array
    {
        foreach ($this->payPalHttpClient()->getSentRequests() as $sentRequest) {
            if ('POST' === $sentRequest['method'] && '/v2/checkout/orders' === $sentRequest['path']) {
                return $sentRequest['body'];
            }
        }

        self::fail('The PayPal order was not created.');
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
