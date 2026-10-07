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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\Response;

final class PaymentRequestPayPageTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    public function test_it_shows_the_paypal_payment_page_of_the_order_it_pays(): void
    {
        $this->payPalOrder();

        $this->client->request('GET', '/en_US/order/TOKEN/pay');
        $payUrl = (string) $this->client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#/en_US/payment-request/pay/[0-9a-f-]{36}$#', $payUrl);

        $this->client->request('GET', $payUrl);
        $response = $this->client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString(sprintf('/en_US/paypal/payment-requests/%s/order', basename($payUrl)), (string) $response->getContent());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('publickey-credentials-get', (string) $response->headers->get('Permissions-Policy'));
    }

    public function test_it_sends_the_payer_back_to_a_fresh_payment_page_after_an_abandoned_attempt(): void
    {
        $payment = $this->payPalOrder();
        /** @var OrderInterface $order */
        $order = $payment->getOrder();
        $order->setShippingAddress($this->shippingAddress());
        $payment->setAmount($order->getTotal());
        $payment->setDetails(['status' => 'CAPTURED', 'paypal_order_id' => 'PAYPAL_ORDER_ID', 'reference_id' => 'REFERENCE_ID']);
        $hash = $this->paymentRequestHash($payment, PaymentRequestInterface::STATE_PROCESSING);

        $this->payPalApi()->mockUpdateOrderAddress();
        $this->payPalApi()->mockCaptureOfUnapprovedOrder();
        $this->client->request('GET', sprintf('/en_US/payment-request/pay/%s', $hash));

        self::assertTrue($this->client->getResponse()->isRedirect('/en_US/order/TOKEN/pay'));
    }

    public function test_it_asks_the_payer_to_authenticate_the_card_again_when_3d_secure_did_not_finish(): void
    {
        $hash = $this->cardPaymentRequestHash();

        $this->payPalApi()->mockCardOrderDetails(authenticationStatus: 'U');
        $this->client->request('GET', sprintf('/en_US/payment-request/pay/%s', $hash));

        self::assertTrue($this->client->getResponse()->isRedirect('/en_US/order/TOKEN/pay'));
        self::assertSame(['sylius_paypal.three_d_secure_retry'], $this->client->getRequest()->getSession()->getFlashBag()->peek('error'));
        self::assertSame(PaymentInterface::STATE_NEW, $this->reloadedPayment()->getState());
    }

    public function test_it_sends_the_payer_to_the_order_when_3d_secure_refused_the_card(): void
    {
        $hash = $this->cardPaymentRequestHash();

        $this->payPalApi()->mockCardOrderDetails(authenticationStatus: 'N');
        $this->client->request('GET', sprintf('/en_US/payment-request/pay/%s', $hash));

        self::assertTrue($this->client->getResponse()->isRedirect('/en_US/order/TOKEN'));
        self::assertSame(['sylius_paypal.three_d_secure_declined'], $this->client->getRequest()->getSession()->getFlashBag()->peek('error'));
        self::assertNotNull($this->reloadedPayment(PaymentInterface::STATE_FAILED));
    }

    public function test_it_captures_a_card_payment_3d_secure_authenticated(): void
    {
        $hash = $this->cardPaymentRequestHash();

        $this->payPalApi()->mockCardOrderDetails();
        $this->payPalApi()->mockUpdateOrderAddress();
        $this->payPalApi()->mockCapture();
        $this->payPalApi()->mockOrderDetailsWithCapture(value: number_format($this->reloadedPayment()->getAmount() / 100, 2, '.', ''));
        $this->client->request('GET', sprintf('/en_US/payment-request/pay/%s', $hash));

        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadedPayment(PaymentInterface::STATE_COMPLETED)?->getState());
    }

    public function test_it_completes_a_trustly_payment_paypal_captured_once_the_payer_comes_back_from_the_bank(): void
    {
        $hash = $this->redirectPaymentRequestHash();

        $this->payPalApi()->mockOrderDetailsWithCapture(value: number_format($this->reloadedPayment()->getAmount() / 100, 2, '.', ''));
        $this->client->request('GET', sprintf('/en_US/payment-request/pay/%s', $hash));

        self::assertNotNull($this->reloadedPayment(PaymentInterface::STATE_COMPLETED));
    }

    public function test_it_leaves_a_trustly_payment_the_bank_has_not_settled_yet_to_the_webhook(): void
    {
        $hash = $this->redirectPaymentRequestHash();

        $this->payPalApi()->mockOrderDetailsWithCapture(captureStatus: 'PENDING', value: number_format($this->reloadedPayment()->getAmount() / 100, 2, '.', ''));
        $this->client->request('GET', sprintf('/en_US/payment-request/pay/%s', $hash));

        self::assertNotNull($this->reloadedPayment(PaymentInterface::STATE_PROCESSING));
        self::assertTrue($this->client->getResponse()->isRedirect('/en_US/order/thank-you'));
        self::assertSame(['sylius_paypal.payment_pending'], $this->client->getRequest()->getSession()->getFlashBag()->peek('info'));
    }

    public function test_it_sends_the_payer_who_cancelled_at_the_bank_back_to_a_fresh_payment_page(): void
    {
        $hash = $this->redirectPaymentRequestHash();

        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', ['status' => 'PAYER_ACTION_REQUIRED']);
        $this->client->request('GET', sprintf('/en_US/payment-request/pay/%s', $hash));

        self::assertTrue($this->client->getResponse()->isRedirect('/en_US/order/TOKEN/pay'));
        self::assertNotNull($this->reloadedPayment());
    }

    public function test_it_tells_the_payer_who_cancelled_at_the_bank_that_the_payment_was_cancelled(): void
    {
        $hash = $this->redirectPaymentRequestHash();

        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', ['status' => 'PAYER_ACTION_REQUIRED']);
        $this->client->request('GET', sprintf('/en_US/payment-request/pay/%s?payer_cancelled=1&errorcode=payment_error', $hash));

        self::assertTrue($this->client->getResponse()->isRedirect('/en_US/order/TOKEN/pay'));
        self::assertSame(['sylius_paypal.payment_cancelled'], $this->client->getRequest()->getSession()->getFlashBag()->peek('info'));
    }

    public function test_it_tells_the_payer_the_bank_refused_that_something_went_wrong(): void
    {
        $hash = $this->redirectPaymentRequestHash();

        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', ['status' => 'PAYER_ACTION_REQUIRED']);
        $this->client->request('GET', sprintf('/en_US/payment-request/pay/%s?payer_cancelled=1&errorcode=processing_error', $hash));

        self::assertTrue($this->client->getResponse()->isRedirect('/en_US/order/TOKEN/pay'));
        self::assertSame(['sylius_paypal.something_went_wrong'], $this->client->getRequest()->getSession()->getFlashBag()->peek('error'));
    }

    private function redirectPaymentRequestHash(): string
    {
        $payment = $this->payPalOrder();
        /** @var OrderInterface $order */
        $order = $payment->getOrder();
        $payment->setAmount($order->getTotal());
        $payment->setDetails([
            'status' => 'CAPTURED',
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'reference_id' => 'REFERENCE_ID',
            'payment_source' => 'trustly',
            'payer_action_url' => 'https://www.sandbox.paypal.com/payment/trustly?token=PAYPAL_ORDER_ID',
        ]);

        return $this->paymentRequestHash($payment, PaymentRequestInterface::STATE_PROCESSING);
    }

    private function cardPaymentRequestHash(): string
    {
        $payment = $this->payPalOrder();
        /** @var OrderInterface $order */
        $order = $payment->getOrder();
        $order->setShippingAddress($this->shippingAddress());
        $payment->setAmount($order->getTotal());
        $payment->setDetails(['status' => 'CAPTURED', 'paypal_order_id' => 'PAYPAL_ORDER_ID', 'reference_id' => 'REFERENCE_ID', 'payment_source' => 'card']);

        return $this->paymentRequestHash($payment, PaymentRequestInterface::STATE_PROCESSING);
    }

    private function reloadedPayment(string $state = PaymentInterface::STATE_NEW): ?PaymentInterface
    {
        $this->getEntityManager()->clear();

        /** @var OrderInterface $order */
        $order = self::getContainer()->get('sylius.repository.order')->findOneBy(['tokenValue' => 'TOKEN']);

        return $order->getLastPayment($state);
    }

    private function payPalOrder(): PaymentInterface
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml']);

        /** @var PaymentInterface $payment */
        $payment = $fixtures['paypal_payment'];

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $gatewayConfig->setUsePayum(false);
        $gatewayConfig->setConfig(array_diff_key($gatewayConfig->getConfig(), ['use_authorize' => true]));

        $this->getEntityManager()->flush();

        return $payment;
    }

    private function paymentRequestHash(PaymentInterface $payment, string $state): string
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        $paymentRequest = self::getContainer()->get('sylius.factory.payment_request')->create($payment, $paymentMethod);
        $paymentRequest->setAction(PaymentRequestInterface::ACTION_CAPTURE);
        $paymentRequest->setState($state);

        $this->getEntityManager()->persist($paymentRequest);
        $this->getEntityManager()->flush();

        return (string) $paymentRequest->getId();
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
}
