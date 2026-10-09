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
