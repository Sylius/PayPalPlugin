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
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Response;
use Tests\Sylius\PayPalPlugin\Service\DummyRefundPaymentApi;

final class RefundPayPalPaymentTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    private OrderInterface $order;

    private PaymentInterface $payment;

    private AdminUserInterface $admin;

    protected function setUp(): void
    {
        parent::setUp();
        DummyRefundPaymentApi::reset();

        $fixtures = $this->loadFixturesFromFiles([
            'resources/shop.yaml',
            'resources/shipping.yaml',
            'resources/shippable_paypal_order.yaml',
        ]);
        $this->order = $fixtures['shippable_order'];
        $this->payment = $fixtures['shippable_paypal_payment'];
        $this->admin = $fixtures['admin'];
        $this->admin->addRole('ROLE_API_ACCESS');
        $this->getEntityManager()->flush();
    }

    protected function tearDown(): void
    {
        DummyRefundPaymentApi::reset();

        parent::tearDown();
    }

    public function test_it_refunds_the_payment_through_paypal_from_the_order_page(): void
    {
        $form = $this->refundForm();
        $this->payPalApi()->mockOrderDetailsWithCapture();
        $this->client->submit($form);

        self::assertTrue($this->client->getResponse()->isRedirect(sprintf('/admin/orders/%d', $this->order->getId())));
        self::assertSame(['sylius.payment.refunded'], $this->client->getRequest()->getSession()->getFlashBag()->peek('success'));
        self::assertSame(['CAPTURE_ID'], DummyRefundPaymentApi::$refundedPaymentIds);

        $payment = $this->reloadPayment();
        self::assertSame(PaymentInterface::STATE_REFUNDED, $payment->getState());
        self::assertTrue(PayPalPaymentDetails::fromPayment($payment)->isRefunded());

        $paymentRequests = $this->refundPaymentRequests();
        self::assertCount(1, $paymentRequests);
        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $paymentRequests[0]->getState());
        self::assertSame([$paymentRequests[0]->getId()], DummyRefundPaymentApi::$requestIds);
    }

    public function test_it_keeps_the_payment_completed_and_the_failed_request_when_paypal_rejects_the_refund(): void
    {
        $form = $this->refundForm();
        $this->payPalApi()->mockOrderDetailsWithCapture(captureId: DummyRefundPaymentApi::REJECTED_CAPTURE_ID);
        $this->client->submit($form);

        $flashes = $this->client->getRequest()->getSession()->getFlashBag()->peek('error');
        self::assertSame('sylius_paypal.payment_not_refunded', $flashes[0]['message']);
        self::assertStringContainsString('REFUND_AMOUNT_EXCEEDED', $flashes[0]['parameters']['%reason%']);
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadPayment()->getState());

        $paymentRequests = $this->refundPaymentRequests();
        self::assertCount(1, $paymentRequests);
        self::assertSame(PaymentRequestInterface::STATE_FAILED, $paymentRequests[0]->getState());
        self::assertStringContainsString('REFUND_AMOUNT_EXCEEDED', $paymentRequests[0]->getResponseData()['reason']);
    }

    public function test_it_asks_paypal_about_the_same_pending_refund_when_the_admin_refunds_again(): void
    {
        $form = $this->refundForm();
        $this->payPalApi()->mockOrderDetailsWithCapture(captureId: DummyRefundPaymentApi::PENDING_CAPTURE_ID);
        $this->client->submit($form);

        self::assertSame(['sylius_paypal.payment_refund_pending'], $this->client->getRequest()->getSession()->getFlashBag()->peek('info'));
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadPayment()->getState());

        $this->payPalApi()->mockOrderDetailsWithCapture(captureId: DummyRefundPaymentApi::PENDING_CAPTURE_ID);
        $this->client->submit($form);

        $paymentRequests = $this->refundPaymentRequests();
        self::assertCount(1, $paymentRequests);
        self::assertSame(PaymentRequestInterface::STATE_PROCESSING, $paymentRequests[0]->getState());
        self::assertSame([$paymentRequests[0]->getId(), $paymentRequests[0]->getId()], DummyRefundPaymentApi::$requestIds);
    }

    public function test_it_refunds_the_payment_through_paypal_from_the_admin_api(): void
    {
        $this->payPalApi()->mockOrderDetailsWithCapture();

        $this->patch(sprintf('/api/v2/admin/payments/%d/paypal-refund', $this->payment->getId()));

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_REFUNDED, $this->reloadPayment()->getState());
        self::assertSame(['CAPTURE_ID'], DummyRefundPaymentApi::$refundedPaymentIds);
    }

    public function test_it_does_not_let_the_core_refund_skip_paypal(): void
    {
        $this->patch(sprintf('/api/v2/admin/payments/%d/refund', $this->payment->getId()));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadPayment()->getState());
        self::assertSame([], DummyRefundPaymentApi::$requestIds);
    }

    private function refundForm(): Form
    {
        $this->client->loginUser($this->admin, 'admin');
        $crawler = $this->client->request('GET', sprintf('/admin/orders/%d', $this->order->getId()));

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $buttons = $crawler->filter(sprintf('[data-test-refund-payment="%d"]', $this->payment->getId()));
        self::assertCount(1, $buttons);

        return $buttons->form();
    }

    private function patch(string $uri): void
    {
        $token = self::getContainer()->get('lexik_jwt_authentication.jwt_manager')->create($this->admin);

        $this->client->request('PATCH', $uri, server: [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], content: '{}');
    }

    private function reloadPayment(): PaymentInterface
    {
        self::getContainer()->get('sylius.manager.payment')->clear();

        /** @var PaymentInterface $payment */
        $payment = self::getContainer()->get('sylius.repository.payment')->find($this->payment->getId());

        return $payment;
    }

    /** @return list<PaymentRequestInterface> */
    private function refundPaymentRequests(): array
    {
        self::getContainer()->get('sylius.manager.payment_request')->clear();

        /** @var list<PaymentRequestInterface> $paymentRequests */
        $paymentRequests = self::getContainer()->get('sylius.repository.payment_request')->findBy([
            'payment' => $this->payment->getId(),
            'action' => PaymentRequestInterface::ACTION_REFUND,
        ]);

        return $paymentRequests;
    }
}
