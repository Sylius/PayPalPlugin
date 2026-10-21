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
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\PayPalPlugin\Command\CaptureAuthorizationPaymentRequest;
use Sylius\PayPalPlugin\CommandProvider\CaptureAuthorizationPaymentRequestCommandProvider;

final class CaptureAuthorizationPaymentRequestTest extends JsonApiTestCase
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

    public function test_it_captures_the_authorization_at_paypal_and_completes_the_payment(): void
    {
        $hash = $this->captureRequestHash();

        $this->payPalApi()->mockOrderDetailsWithAuthorization();
        $this->payPalApi()->mockCaptureAuthorization(requestId: $hash);
        $this->payPalApi()->mockOrderDetailsWithCapturedAuthorization();
        $this->dispatchCapture($hash);

        $payment = $this->reloadedPayment();
        self::assertSame(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertSame('CAPTURE_ID', $payment->getDetails()['transaction_id']);
        self::assertSame('CAPTURED', $payment->getDetails()['authorization_status']);
        self::assertSame(OrderPaymentStates::STATE_PAID, $payment->getOrder()?->getPaymentState());
        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $this->paymentRequest($hash)->getState());
    }

    public function test_it_keeps_the_payment_authorized_and_fails_the_request_when_paypal_rejects_the_capture(): void
    {
        $hash = $this->captureRequestHash();

        $this->payPalApi()->mockOrderDetailsWithAuthorization();
        $this->payPalHttpClient()->addExpectation('POST', 'v2/payments/authorizations/AUTHORIZATION_ID/capture', [
            'name' => 'UNPROCESSABLE_ENTITY',
            'details' => [['issue' => 'AUTHORIZATION_EXPIRED']],
            'debug_id' => 'DEBUG_ID',
        ], 422);
        $this->dispatchCapture($hash);

        self::assertSame(PaymentInterface::STATE_AUTHORIZED, $this->reloadedPayment()->getState());
        $paymentRequest = $this->paymentRequest($hash);
        self::assertSame(PaymentRequestInterface::STATE_FAILED, $paymentRequest->getState());
        self::assertStringContainsString('AUTHORIZATION_EXPIRED', (string) $paymentRequest->getResponseData()['reason']);
    }

    private function captureRequestHash(): string
    {
        $paymentRequest = self::getContainer()->get('sylius.factory.payment_request')->create($this->payment, $this->payment->getMethod());
        $paymentRequest->setAction(CaptureAuthorizationPaymentRequestCommandProvider::ACTION);
        self::getContainer()->get('sylius_abstraction.state_machine')->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_PROCESS);

        $this->getEntityManager()->persist($paymentRequest);
        $this->getEntityManager()->flush();

        return (string) $paymentRequest->getId();
    }

    private function dispatchCapture(string $hash): void
    {
        self::getContainer()->get('sylius.payment_request.command_bus')->dispatch(new CaptureAuthorizationPaymentRequest($hash));
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
