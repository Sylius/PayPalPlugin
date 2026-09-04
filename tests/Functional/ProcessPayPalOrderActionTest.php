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
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;
use Sylius\PayPalPlugin\Processor\PaymentCompleteProcessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tests\Sylius\PayPalPlugin\Service\FakeOrderDetailsApi;

final class ProcessPayPalOrderActionTest extends JsonApiTestCase
{
    public function test_it_completes_the_order_in_one_call_when_the_buyer_approves_in_the_wallet(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart'];

        $this->mockOrderDetailsApi([
            'payer' => [
                'email_address' => 'oliver.queen@star-city.com',
                'name' => ['given_name' => 'Oliver', 'surname' => 'Queen'],
                'phone' => ['phone_number' => ['national_number' => '15551234567']],
                'address' => ['country_code' => 'US'],
            ],
            'purchase_units' => [[
                'amount' => ['value' => '0.60'],
                'shipping' => [
                    'name' => ['full_name' => 'Oliver Queen'],
                    'address' => [
                        'address_line_1' => '1 Star City Plaza',
                        'admin_area_2' => 'Star City',
                        'postal_code' => '10001',
                        'country_code' => 'US',
                    ],
                ],
            ]],
        ]);
        $this->mockSuccessfulPaymentCompleteProcessor();

        $orderId = $order->getId();
        $content = $this->processPayPalOrder($orderId);
        $order = $this->refreshOrder($orderId);

        $this->assertSame($orderId, $content['orderID']);
        $this->assertSame($this->generateUrl('sylius_shop_order_thank_you'), $content['return_url']);
        $this->assertSame('completed', $order->getCheckoutState());

        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment();
        $this->assertSame(PaymentInterface::STATE_COMPLETED, $payment->getState());
    }

    public function test_it_creates_a_new_customer_from_the_pay_pal_payer_data_when_the_order_has_none_yet(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_cart_without_customer.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['new_cart_without_customer'];

        $this->mockOrderDetailsApi([
            'payer' => [
                'email_address' => 'new.buyer@example.com',
                'name' => ['given_name' => 'Jane', 'surname' => 'Doe'],
                'phone' => ['phone_number' => ['national_number' => '15559876543']],
                'address' => ['country_code' => 'US'],
            ],
            'purchase_units' => [[
                'amount' => ['value' => '0.60'],
                'shipping' => [
                    'name' => ['full_name' => 'Jane Doe'],
                    'address' => [
                        'address_line_1' => '42 Wallaby Way',
                        'admin_area_2' => 'Sydney',
                        'postal_code' => '20500',
                        'country_code' => 'US',
                    ],
                ],
            ]],
        ]);
        $this->mockSuccessfulPaymentCompleteProcessor();

        $orderId = $order->getId();
        $content = $this->processPayPalOrder($orderId);
        $order = $this->refreshOrder($orderId);

        $this->assertSame($this->generateUrl('sylius_shop_order_thank_you'), $content['return_url']);

        /** @var CustomerInterface $customer */
        $customer = $order->getCustomer();
        $this->assertNotNull($customer);
        $this->assertSame('new.buyer@example.com', $customer->getEmail());
        $this->assertSame('15559876543', $customer->getPhoneNumber());
    }

    /** @return array<string, mixed> */
    private function processPayPalOrder(int $orderId): array
    {
        $this->client->request(
            'POST',
            '/en_US/process-pay-pal-order/',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['payPalOrderId' => 'PAYPAL_ORDER_ID', 'orderId' => $orderId]),
        );

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    private function refreshOrder(int $orderId): OrderInterface
    {
        /** @var OrderInterface $order */
        $order = self::getContainer()->get('sylius.repository.order')->find($orderId);

        return $order;
    }

    /** @param array<string, mixed> $orderDetails */
    private function mockOrderDetailsApi(array $orderDetails): void
    {
        self::getContainer()->set('sylius_paypal.api.order_details', new FakeOrderDetailsApi($orderDetails));
    }

    private function mockSuccessfulPaymentCompleteProcessor(): void
    {
        self::getContainer()->set('sylius_paypal.processor.payment_complete', new class() implements PaymentCompleteProcessorInterface {
            public function completePayment(PaymentInterface $payment): void
            {
                $payment->setDetails(['status' => StatusAction::STATUS_COMPLETED]);
            }
        });
    }

    private function generateUrl(string $route): string
    {
        /** @var UrlGeneratorInterface $router */
        $router = self::getContainer()->get('router');

        return $router->generate($route, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
