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
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Response;

final class ShopApiPaymentConfigurationTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    public function test_it_provides_the_web_sdk_configuration_of_a_paypal_payment(): void
    {
        /** @var PaymentInterface $payment */
        $payment = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml'])['paypal_payment'];

        self::assertSame([
            'webSdkScriptUrl' => 'https://www.paypal.com/web-sdk/v6/core',
            'webSdkInstanceConfig' => [
                'clientId' => 'CLIENT_ID',
                'components' => ['paypal-payments', 'card-fields'],
                'pageType' => 'checkout',
                'partnerAttributionId' => 'PARTNER_ATTRIBUTION_ID',
                'locale' => 'en-US',
            ],
            'amount' => '0.40',
            'currency' => 'USD',
            'countryCode' => 'US',
            'languageCode' => 'en',
            'sandbox' => false,
            'paymentSources' => ['paypal', 'card'],
            'payLaterEnabled' => false,
            'messagingInstanceConfig' => null,
        ], $this->requestConfiguration($payment));
    }

    public function test_it_provides_the_pay_later_messaging_configuration_once_the_payment_method_turns_it_on(): void
    {
        /** @var PaymentInterface $payment */
        $payment = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml'])['paypal_payment'];
        $gatewayConfig = $payment->getMethod()->getGatewayConfig();
        $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), ['pay_later_enabled' => true, 'messaging_enabled' => true]));
        $this->getEntityManager()->flush();

        $configuration = $this->requestConfiguration($payment);

        self::assertTrue($configuration['payLaterEnabled']);
        self::assertSame([
            'clientId' => 'CLIENT_ID',
            'components' => ['paypal-messages'],
            'pageType' => 'checkout',
            'partnerAttributionId' => 'PARTNER_ATTRIBUTION_ID',
            'locale' => 'en-US',
        ], $configuration['messagingInstanceConfig']);
    }

    /** @return array<string, mixed> */
    private function requestConfiguration(PaymentInterface $payment): array
    {
        $this->client->request(
            'GET',
            sprintf('/api/v2/shop/orders/TOKEN/payments/%d/configuration', $payment->getId()),
            server: ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
