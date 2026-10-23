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
use Symfony\Component\HttpFoundation\Response;
use Tests\Sylius\PayPalPlugin\Service\DummyOrderDetailsApi;
use Tests\Sylius\PayPalPlugin\Service\DummyRefundPaymentApi;

final class RefundPayPalLateCaptureActionTest extends JsonApiTestCase
{
    private OrderInterface $order;

    private PaymentInterface $payment;

    protected function setUp(): void
    {
        parent::setUp();
        DummyOrderDetailsApi::$captureStatus = 'COMPLETED';
        DummyOrderDetailsApi::$failWith = null;
        DummyOrderDetailsApi::$response = null;
        DummyRefundPaymentApi::$refundedPaymentIds = [];

        $fixtures = $this->loadFixturesFromFiles([
            'resources/shop.yaml',
            'resources/shipping.yaml',
            'resources/shippable_paypal_order.yaml',
        ]);
        $this->order = $fixtures['shippable_order'];
        $this->payment = $fixtures['shippable_paypal_payment'];
        $this->payment->setState(PaymentInterface::STATE_CANCELLED);
        $this->payment->setDetails([
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'paypal_late_capture' => ['id' => 'LATE_CAPTURE_ID', 'amount' => 20, 'currency_code' => 'USD'],
        ]);
        $this->getEntityManager()->flush();

        /** @var AdminUserInterface $admin */
        $admin = $fixtures['admin'];
        $this->client->loginUser($admin, 'admin');
    }

    public function test_it_shows_the_late_capture_and_refunds_it_from_the_order_page(): void
    {
        $this->client->enableProfiler();
        $crawler = $this->client->request('GET', sprintf('/admin/orders/%d', $this->order->getId()));

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter(sprintf('[data-test-paypal-late-capture="%d"]', $this->payment->getId())));

        $form = $crawler->filter(sprintf('[data-test-refund-paypal-late-capture="%d"]', $this->payment->getId()))->form();
        $this->client->submit($form);

        self::assertTrue($this->client->getResponse()->isRedirect(sprintf('/admin/orders/%d', $this->order->getId())));
        self::assertSame(['LATE_CAPTURE_ID'], DummyRefundPaymentApi::$refundedPaymentIds);
        self::assertSame(
            ['sylius_paypal.late_capture_refunded'],
            $this->client->getRequest()->getSession()->getFlashBag()->peek('success'),
        );

        $lateCapture = $this->reloadPayment()->getDetails()['paypal_late_capture'];
        self::assertTrue($lateCapture['refunded']);
        self::assertSame(PaymentInterface::STATE_CANCELLED, $this->reloadPayment()->getState());

        $this->client->submit($form);

        self::assertSame(['LATE_CAPTURE_ID'], DummyRefundPaymentApi::$refundedPaymentIds);
    }

    public function test_it_refuses_a_refund_without_a_valid_csrf_token(): void
    {
        $this->client->request(
            'PUT',
            sprintf('/admin/orders/%d/payments/%d/refund-paypal-capture', $this->order->getId(), $this->payment->getId()),
            ['_csrf_token' => 'INVALID'],
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
        self::assertSame([], DummyRefundPaymentApi::$refundedPaymentIds);
    }

    private function reloadPayment(): PaymentInterface
    {
        self::getContainer()->get('sylius.manager.payment')->clear();

        /** @var PaymentInterface $payment */
        $payment = self::getContainer()->get('sylius.repository.payment')->find($this->payment->getId());

        return $payment;
    }
}
