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
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Processor\PaymentCompleteProcessorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tests\Sylius\PayPalPlugin\Service\FakeOrderDetailsApi;

final class CompletePayPalOrderActionTest extends JsonApiTestCase
{
    public function test_it_captures_the_payment_when_the_card_was_authenticated(): void
    {
        $orderId = $this->loadProcessingOrder();
        $this->mockOrderDetailsApi($this->authenticationResult('Y', 'Y', 'POSSIBLE'));
        $this->mockSuccessfulPaymentCompleteProcessor();

        $content = $this->completePayPalOrder();

        self::assertSame($this->generateUrl('sylius_shop_order_thank_you'), $content['return_url']);
        self::assertSame('PAYPAL_ORDER_ID', $content['orderId']);
        self::assertSame(
            PaymentInterface::STATE_COMPLETED,
            $this->refreshOrder($orderId)->getLastPayment()->getState(),
        );
    }

    public function test_it_captures_a_wallet_payment_that_carries_no_authentication_result(): void
    {
        $orderId = $this->loadProcessingOrder();
        $this->mockOrderDetailsApi(['id' => 'PAYPAL_ORDER_ID', 'status' => 'APPROVED']);
        $this->mockSuccessfulPaymentCompleteProcessor();

        $this->completePayPalOrder();

        self::assertSame(
            PaymentInterface::STATE_COMPLETED,
            $this->refreshOrder($orderId)->getLastPayment()->getState(),
        );
    }

    public function test_it_keeps_the_order_payable_when_paypal_refuses_the_capture(): void
    {
        $orderId = $this->loadProcessingOrder();
        $this->mockOrderDetailsApi($this->authenticationResult('Y', 'Y', 'POSSIBLE'));

        $content = $this->completePayPalOrder();
        $order = $this->refreshOrder($orderId);

        self::assertNotSame($this->generateUrl('sylius_shop_order_thank_you'), $content['return_url']);
        self::assertNull($order->getLastPayment(PaymentInterface::STATE_COMPLETED));
        self::assertNotNull($order->getLastPayment(PaymentInterface::STATE_NEW));
    }

    public function test_it_refuses_to_capture_when_the_authentication_failed(): void
    {
        $orderId = $this->loadProcessingOrder();
        $this->mockOrderDetailsApi($this->authenticationResult('Y', 'N', 'NO'));

        $content = $this->completePayPalOrder();
        $order = $this->refreshOrder($orderId);

        self::assertSame($this->generateUrl('sylius_shop_order_show', ['tokenValue' => 'TOKEN']), $content['return_url']);
        self::assertNull($order->getLastPayment(PaymentInterface::STATE_COMPLETED));
        self::assertNotNull(
            $order->getLastPayment(PaymentInterface::STATE_NEW),
            'A refused authentication must leave the order payable.',
        );
    }

    public function test_it_sends_the_buyer_back_to_the_page_when_the_authentication_can_be_retried(): void
    {
        $this->loadProcessingOrder();
        $this->mockOrderDetailsApi($this->authenticationResult('Y', 'C', 'UNKNOWN'));

        $content = $this->completePayPalOrder();

        self::assertStringContainsString('/pay-with-paypal/TOKEN/', $content['return_url']);
    }

    public function test_it_tells_the_buyer_on_the_payment_page_why_the_payment_did_not_go_through(): void
    {
        $this->loadProcessingOrder();
        $this->mockOrderDetailsApi($this->authenticationResult('Y', 'C', 'UNKNOWN'));

        $content = $this->completePayPalOrder();
        $this->client->request('GET', $content['return_url']);

        self::assertStringContainsString(
            'data-test-sylius-flash-message',
            (string) $this->client->getResponse()->getContent(),
            'The payment page did not render the flash message explaining the failure.',
        );
    }

    public function test_it_refuses_a_paypal_order_id_that_does_not_match_the_payment(): void
    {
        $orderId = $this->loadProcessingOrder();

        $this->client->request(
            'POST',
            '/en_US/complete-pay-pal-order/TOKEN',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['payPalOrderId' => 'ANOTHER_PAYPAL_ORDER_ID']),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertSame(
            PaymentInterface::STATE_PROCESSING,
            $this->refreshOrder($orderId)->getLastPayment()->getState(),
        );
    }

    private function loadProcessingOrder(): int
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

        return $orderId;
    }

    private function completePayPalOrder(): array
    {
        $this->client->request(
            'POST',
            '/en_US/complete-pay-pal-order/TOKEN',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['payPalOrderId' => 'PAYPAL_ORDER_ID']),
        );

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    private function authenticationResult(string $enrollment, string $authentication, string $liabilityShift): array
    {
        return [
            'id' => 'PAYPAL_ORDER_ID',
            'payment_source' => [
                'card' => [
                    'authentication_result' => [
                        'liability_shift' => $liabilityShift,
                        'three_d_secure' => [
                            'enrollment_status' => $enrollment,
                            'authentication_status' => $authentication,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function refreshOrder(int $orderId): OrderInterface
    {
        $manager = self::getContainer()->get('sylius.manager.order');
        $manager->clear();

        /** @var OrderInterface $order */
        $order = self::getContainer()->get('sylius.repository.order')->find($orderId);

        return $order;
    }

    private function mockOrderDetailsApi(array $orderDetails): void
    {
        self::getContainer()->set('sylius_paypal.api.order_details', new FakeOrderDetailsApi($orderDetails));
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

    private function generateUrl(string $route, array $parameters = []): string
    {
        /** @var UrlGeneratorInterface $router */
        $router = self::getContainer()->get('router');

        return $router->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
