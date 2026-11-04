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
    use MocksPayPalApiTrait;

    public function test_it_completes_the_payment_once_paypal_says_the_capture_completed(): void
    {
        $this->payPalApi()->mockOrderDetailsWithCapture();

        $paymentRequest = $this->dispatchStatusPaymentRequest();

        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $paymentRequest->getState());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $paymentRequest->getPayment()->getState());
    }

    public function test_it_leaves_the_payment_processing_while_the_capture_is_pending(): void
    {
        $this->payPalApi()->mockOrderDetailsWithCapture(captureStatus: 'PENDING');

        $paymentRequest = $this->dispatchStatusPaymentRequest();

        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $paymentRequest->getState());
        self::assertSame(PaymentInterface::STATE_PROCESSING, $paymentRequest->getPayment()->getState());
    }

    public function test_it_asks_paypal_nothing_about_a_payment_that_is_already_settled(): void
    {
        $paymentRequest = $this->dispatchStatusPaymentRequest(paymentState: PaymentInterface::STATE_COMPLETED);

        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $paymentRequest->getState());
        self::assertSame([], $paymentRequest->getResponseData());
    }

    public function test_it_fails_when_paypal_cannot_be_reached(): void
    {
        $this->payPalApi()->mockOrderDetailsUnreachable();

        $paymentRequest = $this->dispatchStatusPaymentRequest();

        self::assertSame(PaymentRequestInterface::STATE_FAILED, $paymentRequest->getState());
        self::assertSame(['reason' => 'PayPal could not be reached to read the order.'], $paymentRequest->getResponseData());
        self::assertSame(PaymentInterface::STATE_PROCESSING, $paymentRequest->getPayment()->getState());
    }

    public function test_it_fails_for_a_payment_carrying_no_paypal_order(): void
    {
        $paymentRequest = $this->dispatchStatusPaymentRequest(details: []);

        self::assertSame(PaymentRequestInterface::STATE_FAILED, $paymentRequest->getState());
        self::assertSame(['reason' => 'The payment carries no PayPal order id.'], $paymentRequest->getResponseData());
    }

    /** @param array<string, mixed>|null $details */
    private function dispatchStatusPaymentRequest(
        string $paymentState = PaymentInterface::STATE_PROCESSING,
        ?array $details = null,
    ): PaymentRequestInterface {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/processing_paypal_order.yaml']);

        /** @var PaymentInterface $payment */
        $payment = $fixtures['processing_paypal_payment'];
        $payment->setState($paymentState);
        if (null !== $details) {
            $payment->setDetails($details);
        }

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
