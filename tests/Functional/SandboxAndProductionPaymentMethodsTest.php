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
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;

final class SandboxAndProductionPaymentMethodsTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    public function test_a_production_paypal_payment_method_calls_the_live_api_next_to_a_sandbox_one(): void
    {
        [$payment, $production] = $this->loadPaymentWithBothModes();
        $this->payPalHttpClient()->addExpectation('POST', 'v2/checkout/orders', ['id' => 'PAYPAL_ORDER_ID', 'status' => 'CREATED'], host: 'api.paypal.com');

        $paymentRequest = $this->createPayPalOrder($payment, $production);

        self::assertSame(PaymentRequestInterface::STATE_PROCESSING, $paymentRequest->getState());
    }

    public function test_a_sandbox_paypal_payment_method_calls_the_sandbox_api_next_to_a_production_one(): void
    {
        [$payment, , $sandbox] = $this->loadPaymentWithBothModes();
        $this->payPalHttpClient()->addExpectation('POST', 'v2/checkout/orders', ['id' => 'PAYPAL_ORDER_ID', 'status' => 'CREATED'], host: 'api.sandbox.paypal.com');

        $paymentRequest = $this->createPayPalOrder($payment, $sandbox);

        self::assertSame(PaymentRequestInterface::STATE_PROCESSING, $paymentRequest->getState());
    }

    /** @return array{PaymentInterface, PaymentMethodInterface, PaymentMethodInterface} */
    private function loadPaymentWithBothModes(): array
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml']);

        /** @var PaymentMethodInterface $production */
        $production = $fixtures['paypal'];
        /** @var GatewayConfigInterface $productionGatewayConfig */
        $productionGatewayConfig = $production->getGatewayConfig();

        /** @var GatewayConfigInterface $sandboxGatewayConfig */
        $sandboxGatewayConfig = self::getContainer()->get('sylius.factory.gateway_config')->createNew();
        $sandboxGatewayConfig->setFactoryName($productionGatewayConfig->getFactoryName());
        $sandboxGatewayConfig->setGatewayName('paypal_sandbox');
        if (method_exists($sandboxGatewayConfig, 'setUsePayum')) {
            $sandboxGatewayConfig->setUsePayum(false);
        }
        $sandboxGatewayConfig->setConfig(array_merge($productionGatewayConfig->getConfig(), [
            PayPalGatewayConfig::CLIENT_ID => 'SANDBOX_CLIENT_ID',
            PayPalGatewayConfig::SANDBOX => true,
        ]));

        /** @var PaymentMethodInterface $sandbox */
        $sandbox = self::getContainer()->get('sylius.factory.payment_method')->createNew();
        $sandbox->setCode('PAYPAL_SANDBOX');
        $sandbox->setCurrentLocale('en_US');
        $sandbox->setName('PayPal sandbox');
        $sandbox->setGatewayConfig($sandboxGatewayConfig);
        /** @var ChannelInterface $channel */
        $channel = $fixtures['channel_web'];
        $sandbox->addChannel($channel);

        $this->getEntityManager()->persist($sandbox);
        $this->getEntityManager()->flush();

        /** @var PaymentInterface $payment */
        $payment = $fixtures['paypal_payment'];

        return [$payment, $production, $sandbox];
    }

    private function createPayPalOrder(PaymentInterface $payment, PaymentMethodInterface $paymentMethod): PaymentRequestInterface
    {
        $payment->setMethod($paymentMethod);

        /** @var PaymentRequestInterface $paymentRequest */
        $paymentRequest = self::getContainer()->get('sylius.factory.payment_request')->create($payment, $paymentMethod);
        $paymentRequest->setAction(PaymentRequestInterface::ACTION_CAPTURE);
        $paymentRequest->setPayload(['payment_source' => 'paypal']);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($paymentRequest);
        $entityManager->flush();

        self::getContainer()->get('sylius.announcer.payment_request')->dispatchPaymentRequestCommand($paymentRequest);

        $hash = $paymentRequest->getId();
        $entityManager->clear();

        /** @var PaymentRequestInterface $reloaded */
        $reloaded = self::getContainer()->get('sylius.repository.payment_request')->find($hash);

        return $reloaded;
    }
}
