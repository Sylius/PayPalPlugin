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
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\PayPalPlugin\Command\CaptureAuthorizationPaymentRequest;
use Sylius\PayPalPlugin\CommandProvider\CaptureAuthorizationPaymentRequestCommandProvider;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Response;

final class CaptureAuthorizationPaymentRequestTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    private PaymentInterface $payment;

    private AdminUserInterface $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $fixtures = $this->loadFixturesFromFiles([
            'resources/shop.yaml',
            'resources/shipping.yaml',
            'resources/authorized_paypal_order.yaml',
        ]);
        $this->payment = $fixtures['authorized_paypal_payment'];
        $this->admin = $fixtures['admin'];
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

    public function test_it_captures_the_authorization_from_the_order_page(): void
    {
        $form = $this->completeForm();
        $this->payPalApi()->mockOrderDetailsWithAuthorization();
        $this->payPalApi()->mockCaptureAuthorization();
        $this->payPalApi()->mockOrderDetailsWithCapturedAuthorization();
        $this->client->submit($form);

        self::assertTrue($this->client->getResponse()->isRedirect(sprintf('/admin/orders/%d', (int) $this->payment->getOrder()?->getId())));
        self::assertSame(['sylius.payment.completed'], $this->client->getRequest()->getSession()->getFlashBag()->peek('success'));
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadedPayment()->getState());
    }

    public function test_it_keeps_the_payment_authorized_and_tells_why_when_paypal_rejects_the_capture_from_the_order_page(): void
    {
        $form = $this->completeForm();
        $this->payPalApi()->mockOrderDetailsWithAuthorization();
        $this->payPalHttpClient()->addExpectation('POST', 'v2/payments/authorizations/AUTHORIZATION_ID/capture', [
            'name' => 'UNPROCESSABLE_ENTITY',
            'details' => [['issue' => 'AUTHORIZATION_EXPIRED']],
            'debug_id' => 'DEBUG_ID',
        ], 422);
        $this->client->submit($form);

        $flashes = $this->client->getRequest()->getSession()->getFlashBag()->peek('error');
        self::assertSame('sylius_paypal.payment_not_captured', $flashes[0]['message']);
        self::assertStringContainsString('AUTHORIZATION_EXPIRED', $flashes[0]['parameters']['%reason%']);
        self::assertSame(PaymentInterface::STATE_AUTHORIZED, $this->reloadedPayment()->getState());
    }

    public function test_it_captures_the_authorization_through_the_admin_api(): void
    {
        $this->payPalApi()->mockOrderDetailsWithAuthorization();
        $this->payPalApi()->mockCaptureAuthorization();
        $this->payPalApi()->mockOrderDetailsWithCapturedAuthorization();
        $this->patch(sprintf('/api/v2/admin/payments/%d/paypal-capture', $this->payment->getId()));

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadedPayment()->getState());
    }

    public function test_it_refuses_to_capture_a_payment_that_is_not_authorized_through_the_admin_api(): void
    {
        $this->payment->setState(PaymentInterface::STATE_COMPLETED);
        $this->getEntityManager()->flush();

        $this->patch(sprintf('/api/v2/admin/payments/%d/paypal-capture', $this->payment->getId()));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
    }

    public function test_it_does_not_let_the_core_complete_skip_paypal(): void
    {
        $this->patch(sprintf('/api/v2/admin/payments/%d/complete', $this->payment->getId()));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_AUTHORIZED, $this->reloadedPayment()->getState());
    }

    private function patch(string $uri): void
    {
        $this->admin->addRole('ROLE_API_ACCESS');
        $this->getEntityManager()->flush();

        $this->client->request('PATCH', $uri, server: [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . self::getContainer()->get('lexik_jwt_authentication.jwt_manager')->create($this->admin),
        ], content: '{}');
    }

    private function completeForm(): Form
    {
        $this->client->loginUser($this->admin, 'admin');
        $crawler = $this->client->request('GET', sprintf('/admin/orders/%d', (int) $this->payment->getOrder()?->getId()));

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $buttons = $crawler->filter(sprintf('[data-test-complete-payment="%d"]', $this->payment->getId()));
        self::assertCount(1, $buttons);
        self::assertStringContainsString('capture-paypal-authorization', (string) $buttons->form()->getUri());

        return $buttons->form();
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
