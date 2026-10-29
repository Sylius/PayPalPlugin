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

        $this->client->request(
            'GET',
            sprintf('/api/v2/shop/orders/TOKEN/payments/%d/configuration', $payment->getId()),
            server: ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $configuration = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('https://www.paypal.com/web-sdk/v6/core', $configuration['webSdkScriptUrl']);
        self::assertSame([
            'clientId' => 'CLIENT_ID',
            'components' => ['paypal-payments', 'card-fields'],
            'pageType' => 'checkout',
            'partnerAttributionId' => 'PARTNER_ATTRIBUTION_ID',
            'locale' => 'en-US',
        ], $configuration['webSdkInstanceConfig']);
        self::assertSame('0.40', $configuration['amount']);
        self::assertSame('USD', $configuration['currency']);
        self::assertTrue($configuration['cardEnabled']);
        self::assertTrue($configuration['paylaterEnabled']);
        self::assertFalse($configuration['googlePayEnabled']);
        self::assertArrayNotHasKey('errorPayPalPaymentUrl', $configuration);
        self::assertArrayNotHasKey('cancelPayPalPaymentUrl', $configuration);
    }
}
