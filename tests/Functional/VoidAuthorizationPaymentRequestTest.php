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
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Order\OrderTransitions;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\PayPalPlugin\Command\VoidAuthorizationPaymentRequest;
use Sylius\PayPalPlugin\CommandProvider\VoidAuthorizationPaymentRequestCommandProvider;

final class VoidAuthorizationPaymentRequestTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    private PaymentInterface $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $fixtures = $this->loadFixturesFromFiles([
            'resources/shop.yaml',
            'resources/shipping.yaml',
            'resources/authorized_paypal_order.yaml',
        ]);
        $this->payment = $fixtures['authorized_paypal_payment'];
    }

    public function test_it_voids_the_authorization_at_paypal_and_records_it_on_the_payment(): void
    {
        $hash = $this->voidRequestHash();

        $this->payPalApi()->mockOrderDetailsWithAuthorization();
        $this->payPalApi()->mockVoidAuthorization(requestId: $hash);
        $this->payPalApi()->mockOrderDetailsWithAuthorization(authorizationStatus: 'VOIDED');
        $this->dispatchVoid($hash);

        self::assertSame('VOIDED', $this->reloadedPayment()->getDetails()['authorization_status']);
        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $this->paymentRequest($hash)->getState());
    }

    public function test_it_fails_the_request_with_the_reason_when_paypal_rejects_the_void(): void
    {
        $hash = $this->voidRequestHash();

        $this->payPalApi()->mockOrderDetailsWithAuthorization();
        $this->payPalHttpClient()->addExpectation('POST', 'v2/payments/authorizations/AUTHORIZATION_ID/void', [
            'name' => 'UNPROCESSABLE_ENTITY',
            'details' => [['issue' => 'AUTHORIZATION_ALREADY_CAPTURED']],
            'debug_id' => 'DEBUG_ID',
        ], 422);
        $this->payPalApi()->mockOrderDetailsWithAuthorization();
        $this->dispatchVoid($hash);

        self::assertSame('CREATED', $this->reloadedPayment()->getDetails()['authorization_status']);
        $paymentRequest = $this->paymentRequest($hash);
        self::assertSame(PaymentRequestInterface::STATE_FAILED, $paymentRequest->getState());
        self::assertStringContainsString('AUTHORIZATION_ALREADY_CAPTURED', (string) $paymentRequest->getResponseData()['reason']);
    }

    public function test_it_voids_the_authorization_when_the_admin_cancels_the_order(): void
    {
        $this->payPalApi()->mockOrderDetailsWithAuthorization();
        $this->payPalApi()->mockVoidAuthorization();
        $this->payPalApi()->mockOrderDetailsWithAuthorization(authorizationStatus: 'VOIDED');

        $this->cancelOrder();

        $payment = $this->reloadedPayment();
        self::assertSame(PaymentInterface::STATE_CANCELLED, $payment->getState());
        self::assertSame('VOIDED', $payment->getDetails()['authorization_status']);
        self::assertSame(OrderPaymentStates::STATE_CANCELLED, $payment->getOrder()?->getPaymentState());

        $voidRequests = self::getContainer()->get('sylius.repository.payment_request')->findBy(['action' => VoidAuthorizationPaymentRequestCommandProvider::ACTION]);
        self::assertCount(1, $voidRequests);
        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $voidRequests[0]->getState());
    }

    public function test_it_cancels_the_order_even_when_paypal_rejects_the_void(): void
    {
        $this->payPalApi()->mockOrderDetailsWithAuthorization();
        $this->payPalHttpClient()->addExpectation('POST', 'v2/payments/authorizations/AUTHORIZATION_ID/void', ['name' => 'UNPROCESSABLE_ENTITY', 'debug_id' => 'DEBUG_ID'], 422);
        $this->payPalApi()->mockOrderDetailsWithAuthorization();

        $this->cancelOrder();

        $payment = $this->reloadedPayment();
        self::assertSame(PaymentInterface::STATE_CANCELLED, $payment->getState());
        self::assertSame(OrderInterface::STATE_CANCELLED, $payment->getOrder()?->getState());

        $voidRequests = self::getContainer()->get('sylius.repository.payment_request')->findBy(['action' => VoidAuthorizationPaymentRequestCommandProvider::ACTION]);
        self::assertCount(1, $voidRequests);
        self::assertSame(PaymentRequestInterface::STATE_FAILED, $voidRequests[0]->getState());
    }

    private function cancelOrder(): void
    {
        /** @var OrderInterface $order */
        $order = $this->reloadedPayment()->getOrder();
        self::getContainer()->get('sylius_abstraction.state_machine')->apply($order, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CANCEL);
        $this->getEntityManager()->flush();
    }

    private function voidRequestHash(): string
    {
        $paymentRequest = self::getContainer()->get('sylius.factory.payment_request')->create($this->payment, $this->payment->getMethod());
        $paymentRequest->setAction(VoidAuthorizationPaymentRequestCommandProvider::ACTION);
        self::getContainer()->get('sylius_abstraction.state_machine')->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_PROCESS);

        $this->getEntityManager()->persist($paymentRequest);
        $this->getEntityManager()->flush();

        return (string) $paymentRequest->getId();
    }

    private function dispatchVoid(string $hash): void
    {
        self::getContainer()->get('sylius.payment_request.command_bus')->dispatch(new VoidAuthorizationPaymentRequest($hash));
        $this->getEntityManager()->flush();
    }

    private function reloadedPayment(): PaymentInterface
    {
        $this->getEntityManager()->clear();

        /** @var OrderInterface $order */
        $order = self::getContainer()->get('sylius.repository.order')->findOneBy(['tokenValue' => 'AUTHORIZED_TOKEN']);

        /** @var PaymentInterface $payment */
        $payment = $order->getPayments()->first();

        return $payment;
    }

    private function paymentRequest(string $hash): PaymentRequestInterface
    {
        $this->getEntityManager()->clear();

        /** @var PaymentRequestInterface $paymentRequest */
        $paymentRequest = self::getContainer()->get('sylius.repository.payment_request')->find($hash);

        return $paymentRequest;
    }
}
