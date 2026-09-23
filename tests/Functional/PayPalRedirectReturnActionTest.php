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
use Symfony\Component\HttpFoundation\Response;
use Tests\Sylius\PayPalPlugin\Service\DummyOrderDetailsApi;

final class PayPalRedirectReturnActionTest extends JsonApiTestCase
{
    private const RETURN_NONCE = '0123456789abcdef0123456789abcdef';

    private const CANCEL_NONCE = 'fedcba9876543210fedcba9876543210';

    protected function setUp(): void
    {
        parent::setUp();
        DummyOrderDetailsApi::$captureStatus = 'COMPLETED';
        DummyOrderDetailsApi::$failWith = null;
    }

    public function test_it_settles_the_payment_of_the_payer_action_it_started(): void
    {
        $order = $this->redirectOrder();

        $this->client->request('GET', sprintf('/en_US/paypal/redirect-return/TOKEN/%s', self::RETURN_NONCE));

        self::assertTrue($this->client->getResponse()->isRedirect());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadPayment($order)->getState());
    }

    public function test_it_refuses_a_return_that_carries_another_nonce(): void
    {
        $order = $this->redirectOrder();

        $this->client->request('GET', '/en_US/paypal/redirect-return/TOKEN/ffffffffffffffffffffffffffffffff');

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_PROCESSING, $this->reloadPayment($order)->getState());
    }

    public function test_it_refuses_a_cancellation_that_carries_another_nonce(): void
    {
        $order = $this->redirectOrder();

        $this->client->request('GET', '/en_US/paypal/redirect-cancel/TOKEN/ffffffffffffffffffffffffffffffff');

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_PROCESSING, $this->reloadPayment($order)->getState());
    }

    public function test_it_refuses_a_return_answered_with_the_nonce_that_cancels_it(): void
    {
        $order = $this->redirectOrder();

        $this->client->request('GET', sprintf('/en_US/paypal/redirect-return/TOKEN/%s', self::CANCEL_NONCE));

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_PROCESSING, $this->reloadPayment($order)->getState());
    }

    private function redirectOrder(): OrderInterface
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/redirect_paypal_order.yaml']);

        /** @var OrderInterface $order */
        $order = $fixtures['redirect_order'];

        return $order;
    }

    private function reloadPayment(OrderInterface $order): PaymentInterface
    {
        $manager = self::getContainer()->get('sylius.manager.order');
        $manager->clear();

        /** @var OrderInterface $reloaded */
        $reloaded = self::getContainer()->get('sylius.repository.order')->find($order->getId());

        /** @var PaymentInterface $payment */
        $payment = $reloaded->getLastPayment();

        return $payment;
    }
}
