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
use Sylius\Component\Order\Model\OrderItemInterface;
use Symfony\Component\HttpFoundation\Response;

final class PayWithPayPalFormActionTest extends JsonApiTestCase
{
    public function test_it_renders_the_page_without_the_legacy_paypal_sdk(): void
    {
        $this->requestPaymentPage();
        $response = $this->client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringNotContainsString(
            'paypal.com/sdk/js',
            (string) $response->getContent(),
            'The payment page still loads PayPal JS SDK v5.',
        );
    }

    private function requestPaymentPage(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/processing_paypal_order.yaml']);
        $orderId = (int) $fixtures['processing_order']->getId();

        /** @var OrderInterface $order */
        $order = self::getContainer()->get('sylius.repository.order')->find($orderId);

        /** @var OrderItemInterface $item */
        $item = $order->getItems()->first();
        $item->setUnitPrice(20);
        $order->recalculateItemsTotal();

        self::getContainer()->get('sylius.manager.order')->flush();

        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment();

        $this->client->request('GET', sprintf('/en_US/pay-with-paypal/%s/%s', $order->getTokenValue(), $payment->getId()));
    }
}
