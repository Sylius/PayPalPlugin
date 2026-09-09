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
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Processor\PaymentCompleteProcessorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CompletePayPalOrderFromPaymentPageActionTest extends JsonApiTestCase
{
    /** @test */
    public function it_completes_the_order_when_the_amount_matches(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart'];
        /** @var PaymentInterface $payment */
        $payment = $fixtures['paypal_payment'];

        $this->preparePaymentForCompletion($payment->getId(), $order->getId());
        $this->mockSuccessfulPaymentCompleteProcessor();

        $this->client->request('POST', '/en_US/pay-pal-order-payment-page/TOKEN/complete');

        $response = $this->client->getResponse();
        $content = (array) json_decode((string) $response->getContent(), true);

        $this->assertSame('PAYPAL_ORDER_ID', $content['orderId']);
        $this->assertSame($this->generateUrl('sylius_shop_order_thank_you'), $content['return_url']);

        $order = $this->refreshOrder($order->getId());
        $this->assertSame('completed', $order->getCheckoutState());

        // Not $order->getLastPayment(): completing the order's checkout can attach a fresh cart-state
        // payment for any future purchase, so "last" no longer means "the one we just completed" - refetch
        // the exact payment under test by id instead.
        /** @var PaymentInterface $payment */
        $payment = self::getContainer()->get('sylius.repository.payment')->find($payment->getId());
        $this->assertSame(PaymentInterface::STATE_COMPLETED, $payment->getState());
    }

    /** @test */
    public function it_returns_not_found_for_a_foreign_or_unknown_token(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart'];
        /** @var PaymentInterface $payment */
        $payment = $fixtures['paypal_payment'];

        $this->preparePaymentForCompletion($payment->getId(), $order->getId());

        $this->client->request('POST', '/en_US/pay-pal-order-payment-page/FOREIGN_TOKEN/complete');

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    private function preparePaymentForCompletion(int $paymentId, int $orderId): void
    {
        $paymentManager = self::getContainer()->get('sylius.manager.payment');
        /** @var PaymentInterface $payment */
        $payment = self::getContainer()->get('sylius.repository.payment')->find($paymentId);
        /** @var OrderInterface $order */
        $order = self::getContainer()->get('sylius.repository.order')->find($orderId);

        $payment->setState(PaymentInterface::STATE_PROCESSING);
        $payment->setDetails(['paypal_order_id' => 'PAYPAL_ORDER_ID', 'payment_amount' => $order->getTotal()]);

        $paymentManager->flush();
    }

    private function mockSuccessfulPaymentCompleteProcessor(): void
    {
        self::getContainer()->set('sylius_paypal.processor.payment_complete', new class() implements PaymentCompleteProcessorInterface {
            public function completePayment(PaymentInterface $payment): void
            {
                $payment->setDetails(array_merge($payment->getDetails(), ['status' => StatusAction::STATUS_COMPLETED]));
            }
        });
    }

    private function refreshOrder(int $orderId): OrderInterface
    {
        /** @var OrderInterface $order */
        $order = self::getContainer()->get('sylius.repository.order')->find($orderId);

        return $order;
    }

    private function generateUrl(string $route): string
    {
        /** @var UrlGeneratorInterface $router */
        $router = self::getContainer()->get('router');

        return $router->generate($route, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
