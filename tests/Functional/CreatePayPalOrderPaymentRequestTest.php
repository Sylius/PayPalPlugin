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
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final class CreatePayPalOrderPaymentRequestTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    public function test_it_creates_the_paypal_order_for_the_chosen_payment_source(): void
    {
        $this->payPalApi()->mockCreateOrder('PAYPAL_ORDER_ID');

        $paymentRequest = $this->dispatchCreatePayPalOrderPaymentRequest(['payment_source' => 'card']);

        self::assertSame(PaymentRequestInterface::STATE_PROCESSING, $paymentRequest->getState());
        self::assertSame(['paypal_order_id' => 'PAYPAL_ORDER_ID'], $paymentRequest->getResponseData());

        $payment = $paymentRequest->getPayment();
        self::assertSame(PaymentInterface::STATE_NEW, $payment->getState());
        self::assertSame('PAYPAL_ORDER_ID', $payment->getDetails()['paypal_order_id']);
        self::assertSame('card', $payment->getDetails()['payment_source']);
    }

    public function test_it_waits_for_the_payer_to_choose_a_payment_source(): void
    {
        $paymentRequest = $this->dispatchCreatePayPalOrderPaymentRequest(null);

        self::assertSame(PaymentRequestInterface::STATE_NEW, $paymentRequest->getState());
        self::assertSame([], $paymentRequest->getPayment()->getDetails());
    }

    public function test_it_fails_for_a_payment_source_paypal_does_not_support(): void
    {
        $paymentRequest = $this->dispatchCreatePayPalOrderPaymentRequest(['payment_source' => 'bitcoin']);

        self::assertSame(PaymentRequestInterface::STATE_FAILED, $paymentRequest->getState());
        self::assertSame(['reason' => 'PayPal does not support the requested payment source.'], $paymentRequest->getResponseData());
    }

    public function test_it_fails_when_paypal_refuses_to_create_the_order(): void
    {
        $this->payPalHttpClient()->addExpectation('POST', 'v2/checkout/orders', ['name' => 'UNPROCESSABLE_ENTITY', 'debug_id' => 'DEBUG_ID'], 422);

        $paymentRequest = $this->dispatchCreatePayPalOrderPaymentRequest(['payment_source' => 'paypal']);

        self::assertSame(PaymentRequestInterface::STATE_FAILED, $paymentRequest->getState());
        self::assertSame(['reason' => 'PayPal did not create the order.'], $paymentRequest->getResponseData());
        self::assertSame(PaymentInterface::STATE_NEW, $paymentRequest->getPayment()->getState());
    }

    private function dispatchCreatePayPalOrderPaymentRequest(mixed $payload): PaymentRequestInterface
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml']);

        /** @var PaymentInterface $payment */
        $payment = $fixtures['paypal_payment'];

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $gatewayConfig->setUsePayum(false);

        $paymentRequest = self::getContainer()->get('sylius.factory.payment_request')->create($payment, $paymentMethod);
        $paymentRequest->setAction(PaymentRequestInterface::ACTION_CAPTURE);
        $paymentRequest->setPayload($payload);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($paymentRequest);
        $entityManager->flush();

        self::getContainer()->get('sylius.announcer.payment_request')->dispatchPaymentRequestCommand($paymentRequest);

        $hash = $paymentRequest->getId();
        $entityManager->clear();

        /** @var PaymentRequestInterface $reloaded */
        $reloaded = self::getContainer()->get('sylius.repository.payment_request')->find($hash);

        return $reloaded;
    }
}
