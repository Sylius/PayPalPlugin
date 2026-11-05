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
use Sylius\Component\Core\OrderPaymentStates;

final class OrderPageChangePaymentMethodTest extends JsonApiTestCase
{
    public function test_it_lets_the_buyer_pay_an_order_awaiting_payment(): void
    {
        $page = $this->orderPage(['resources/shop.yaml', 'resources/new_order.yaml'], 'new_order');

        self::assertStringContainsString('name="sylius_checkout_select_payment"', $page);
    }

    public function test_it_does_not_let_the_buyer_pay_again_while_paypal_completes_the_capture(): void
    {
        $page = $this->orderPage(['resources/shop.yaml', 'resources/processing_paypal_order.yaml'], 'processing_order');

        self::assertStringNotContainsString('name="sylius_checkout_select_payment"', $page);
        self::assertStringContainsString('You can no longer change payment method of this order', $page);
    }

    public function test_it_sends_the_buyer_still_at_the_bank_back_to_the_bank(): void
    {
        $page = $this->orderPage(['resources/shop.yaml', 'resources/redirect_paypal_order.yaml'], 'redirect_order');

        self::assertStringNotContainsString('name="sylius_checkout_select_payment"', $page);
        self::assertStringContainsString('data-test-paypal-awaiting-payer-action', $page);
        self::assertStringContainsString('https://www.sandbox.paypal.com/payment/trustly?token=PAYPAL_ORDER_ID', $page);
    }

    /** @param list<string> $fixtureFiles */
    private function orderPage(array $fixtureFiles, string $orderFixture): string
    {
        /** @var OrderInterface $order */
        $order = $this->loadFixturesFromFiles($fixtureFiles)[$orderFixture];
        $order->setPaymentState(OrderPaymentStates::STATE_AWAITING_PAYMENT);
        $this->getEntityManager()->flush();

        $this->client->request('GET', sprintf('/en_US/order/%s', (string) $order->getTokenValue()));
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        return (string) $this->client->getResponse()->getContent();
    }
}
