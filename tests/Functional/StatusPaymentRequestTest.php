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
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final class StatusPaymentRequestTest extends JsonApiTestCase
{
    public function test_it_completes_a_status_payment_request_for_a_payment_carrying_a_paypal_order(): void
    {
        $paymentRequest = $this->dispatchStatusPaymentRequest(['status' => 'CREATED', 'paypal_order_id' => 'PAYPAL_ORDER_ID']);

        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $paymentRequest->getState());
        self::assertSame([], $paymentRequest->getResponseData());
    }

    public function test_it_fails_a_status_payment_request_for_a_payment_carrying_no_paypal_order(): void
    {
        $paymentRequest = $this->dispatchStatusPaymentRequest([]);

        self::assertSame(PaymentRequestInterface::STATE_FAILED, $paymentRequest->getState());
        self::assertSame(['reason' => 'The payment carries no PayPal order id.'], $paymentRequest->getResponseData());
    }

    /** @param array<string, mixed> $details */
    private function dispatchStatusPaymentRequest(array $details): PaymentRequestInterface
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/processing_paypal_cart.yaml']);

        /** @var PaymentInterface $payment */
        $payment = $fixtures['abandoned_paypal_payment'];
        $payment->setDetails($details);

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $gatewayConfig->setUsePayum(false);

        $paymentRequest = self::getContainer()->get('sylius.factory.payment_request')->create($payment, $paymentMethod);
        $paymentRequest->setAction(PaymentRequestInterface::ACTION_STATUS);

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
