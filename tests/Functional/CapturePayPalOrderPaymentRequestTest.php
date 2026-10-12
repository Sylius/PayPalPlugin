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
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final class CapturePayPalOrderPaymentRequestTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    public function test_it_completes_the_payment_paypal_captured(): void
    {
        $paymentRequest = $this->dispatchCapturePayPalOrderPaymentRequest('COMPLETED');

        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $paymentRequest->getState());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $paymentRequest->getPayment()->getState());
        self::assertSame('CAPTURE_ID', $paymentRequest->getPayment()->getDetails()['transaction_id']);
    }

    public function test_it_leaves_a_pending_capture_processing(): void
    {
        $paymentRequest = $this->dispatchCapturePayPalOrderPaymentRequest('PENDING');

        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $paymentRequest->getState());
        self::assertSame(PaymentInterface::STATE_PROCESSING, $paymentRequest->getPayment()->getState());
    }

    public function test_it_fails_the_payment_paypal_declined_to_capture(): void
    {
        $paymentRequest = $this->dispatchCapturePayPalOrderPaymentRequest('DECLINED');

        self::assertSame(PaymentRequestInterface::STATE_FAILED, $paymentRequest->getState());
        self::assertSame(['reason' => 'PayPal reported the capture as DECLINED.'], $paymentRequest->getResponseData());
        self::assertSame(PaymentInterface::STATE_FAILED, $paymentRequest->getPayment()->getState());
    }

    public function test_it_completes_a_payment_captured_for_another_amount_and_records_what_paypal_took(): void
    {
        $paymentRequest = $this->dispatchCapturePayPalOrderPaymentRequest('COMPLETED', capturedValue: '0.01');

        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $paymentRequest->getState());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $paymentRequest->getPayment()->getState());
        self::assertSame(1, $paymentRequest->getPayment()->getDetails()['captured_amount']);
        self::assertSame('USD', $paymentRequest->getPayment()->getDetails()['captured_currency_code']);
    }

    public function test_it_cancels_an_attempt_the_payer_never_approved_and_leaves_the_payment_payable(): void
    {
        $paymentRequest = $this->dispatchCapturePayPalOrderPaymentRequest(approved: false);

        self::assertSame(PaymentRequestInterface::STATE_CANCELLED, $paymentRequest->getState());
        self::assertSame(['reason' => 'The payer did not approve the PayPal order.'], $paymentRequest->getResponseData());
        self::assertSame(PaymentInterface::STATE_NEW, $paymentRequest->getPayment()->getState());
        self::assertSame('CAPTURED', $paymentRequest->getPayment()->getDetails()['status']);
    }

    private function dispatchCapturePayPalOrderPaymentRequest(
        string $captureStatus = 'COMPLETED',
        ?string $capturedValue = null,
        bool $approved = true,
    ): PaymentRequestInterface {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml']);

        /** @var PaymentInterface $payment */
        $payment = $fixtures['paypal_payment'];
        /** @var OrderInterface $order */
        $order = $payment->getOrder();
        $order->setShippingAddress($this->shippingAddress());
        $payment->setAmount($order->getTotal());
        $payment->setDetails([
            'status' => 'CAPTURED',
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'reference_id' => 'REFERENCE_ID',
            'payment_amount' => $order->getTotal(),
            'payment_source' => 'paypal',
        ]);

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $gatewayConfig->setUsePayum(false);

        $paymentRequest = self::getContainer()->get('sylius.factory.payment_request')->create($payment, $paymentMethod);
        $paymentRequest->setAction(PaymentRequestInterface::ACTION_CAPTURE);
        $paymentRequest->setState(PaymentRequestInterface::STATE_PROCESSING);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($paymentRequest);
        $entityManager->flush();

        $this->payPalApi()->mockUpdateOrderAddress();
        if ($approved) {
            $this->payPalApi()->mockCapture();
            $this->payPalApi()->mockOrderDetailsWithCapture(
                captureStatus: $captureStatus,
                value: $capturedValue ?? number_format($order->getTotal() / 100, 2, '.', ''),
            );
        } else {
            $this->payPalApi()->mockCaptureOfUnapprovedOrder();
        }

        self::getContainer()->get('sylius.announcer.payment_request')->dispatchPaymentRequestCommand($paymentRequest);

        $hash = $paymentRequest->getId();
        $entityManager->clear();

        /** @var PaymentRequestInterface $reloaded */
        $reloaded = self::getContainer()->get('sylius.repository.payment_request')->find($hash);

        return $reloaded;
    }

    private function shippingAddress(): AddressInterface
    {
        /** @var AddressInterface $address */
        $address = self::getContainer()->get('sylius.factory.address')->createNew();
        $address->setFirstName('Oliver');
        $address->setLastName('Queen');
        $address->setStreet('Star City 1');
        $address->setCity('Star City');
        $address->setPostcode('90210');
        $address->setCountryCode('US');

        return $address;
    }
}
