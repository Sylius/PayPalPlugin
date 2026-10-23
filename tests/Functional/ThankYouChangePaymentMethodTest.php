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
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;

final class ThankYouChangePaymentMethodTest extends JsonApiTestCase
{
    public function test_it_offers_no_payment_method_change_for_a_paid_order_whose_last_payment_is_not_the_completed_one(): void
    {
        /** @var OrderInterface $order */
        $order = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/completed_paypal_order.yaml'])['completed_order'];
        $orphanedPayment = new Payment();
        $orphanedPayment->setMethod($order->getLastPayment()?->getMethod());
        $orphanedPayment->setCurrencyCode('USD');
        $orphanedPayment->setAmount(20);
        $orphanedPayment->setState(PaymentInterface::STATE_CART);
        $order->addPayment($orphanedPayment);
        $this->getEntityManager()->flush();

        self::assertStringNotContainsString('Change payment method', $this->thankYouPage($order));
    }

    public function test_it_offers_a_payment_method_change_for_an_order_awaiting_payment(): void
    {
        /** @var OrderInterface $order */
        $order = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/processing_paypal_order.yaml'])['processing_order'];
        $order->setPaymentState(OrderPaymentStates::STATE_AWAITING_PAYMENT);
        $this->getEntityManager()->flush();

        self::assertStringContainsString('Change payment method', $this->thankYouPage($order));
    }

    public function test_it_offers_no_payment_method_change_while_the_bank_transfer_is_on_its_way(): void
    {
        /** @var OrderInterface $order */
        $order = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/redirect_paypal_order.yaml'])['redirect_order'];
        $order->setPaymentState(OrderPaymentStates::STATE_AWAITING_PAYMENT);
        $this->getEntityManager()->flush();

        self::assertStringNotContainsString('Change payment method', $this->thankYouPage($order));
    }

    private function thankYouPage(OrderInterface $order): string
    {
        /** @var SessionFactoryInterface $sessionFactory */
        $sessionFactory = self::getContainer()->get('session.factory');
        $session = $sessionFactory->createSession();
        $session->set('sylius_order_id', $order->getId());
        $session->save();
        $this->client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));

        $this->client->request('GET', '/en_US/order/thank-you');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        return (string) $this->client->getResponse()->getContent();
    }
}
