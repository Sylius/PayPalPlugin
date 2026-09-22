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
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\Model\OrderItemInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Sylius\PayPalPlugin\Service\FakeFindEligibleMethodsApi;

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

    public function test_it_tells_the_payer_that_paypal_processes_their_data(): void
    {
        $this->requestPaymentPage();
        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString(
            'By paying with your card, you acknowledge that PayPal will process your data according to the PayPal Privacy Statement available at PayPal.com.',
            $content,
            'The payment page is missing the wording PayPal prescribes in SDD 4.1.4.',
        );
        self::assertStringContainsString('https://www.paypal.com/myaccount/privacy/privacyhub', $content);
    }

    public function test_it_renders_no_google_pay_tile_until_the_channel_opts_in(): void
    {
        $this->requestPaymentPage();

        self::assertStringNotContainsString(
            'sylius--paypal-plugin--paypal-payment-google-pay',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function test_it_renders_the_google_pay_tile_once_the_channel_opts_in(): void
    {
        $this->requestPaymentPage(googlePayEnabled: true);
        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('sylius--paypal-plugin--paypal-payment-google-pay', $content);
        self::assertStringContainsString('googlepay-payments', $content);
        self::assertStringContainsString(
            'data-sylius--paypal-plugin--paypal-payment-google-pay-language-code-value="en"',
            $content,
        );
    }

    public function test_it_renders_no_trustly_tile_until_the_channel_opts_in(): void
    {
        $this->requestPaymentPage();

        self::assertStringNotContainsString(
            'sylius--paypal-plugin--paypal-payment-redirect-button',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function test_it_renders_the_trustly_tile_once_the_channel_opts_in_and_paypal_says_it_is_eligible(): void
    {
        FakeFindEligibleMethodsApi::$eligibleMethods = ['trustly' => []];

        $this->requestPaymentPage(trustlyEnabled: true);
        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('sylius--paypal-plugin--paypal-payment-redirect-button', $content);
        self::assertStringContainsString(
            'data-sylius--paypal-plugin--paypal-payment-redirect-button-payment-source-value="trustly"',
            $content,
        );
        self::assertStringContainsString('Pay with Trustly', $content);
        self::assertStringContainsString(
            'paypalobjects.com/images/checkout/alternative_payments/paypal_trustly_color.svg',
            $content,
        );
    }

    public function test_it_renders_no_trustly_tile_when_paypal_says_it_is_not_eligible(): void
    {
        FakeFindEligibleMethodsApi::$eligibleMethods = [];

        $this->requestPaymentPage(trustlyEnabled: true);

        self::assertStringNotContainsString(
            'sylius--paypal-plugin--paypal-payment-redirect-button',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    private function requestPaymentPage(bool $googlePayEnabled = false, bool $trustlyEnabled = false): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/processing_paypal_order.yaml']);
        $orderId = (int) $fixtures['processing_order']->getId();

        if ($googlePayEnabled) {
            $this->enableGatewayConfig(['google_pay_enabled' => true]);
        }

        if ($trustlyEnabled) {
            $this->enableGatewayConfig(['trustly_enabled' => true]);
        }

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

    /** @param array<string, mixed> $config */
    private function enableGatewayConfig(array $config): void
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = self::getContainer()->get('sylius.repository.payment_method')->findOneBy(['code' => 'PAYPAL']);
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), $config));

        self::getContainer()->get('doctrine.orm.entity_manager')->flush();
    }
}
