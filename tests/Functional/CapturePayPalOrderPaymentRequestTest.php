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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

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

    public function test_it_captures_the_order_once_when_paypal_fails_the_first_attempt(): void
    {
        $paymentRequest = $this->dispatchCapturePayPalOrderPaymentRequest('COMPLETED', paypalFailsFirst: true);

        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $paymentRequest->getState());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $paymentRequest->getPayment()->getState());
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

    public function test_it_asks_for_another_payment_method_when_paypal_declines_the_instrument(): void
    {
        $paymentRequest = $this->dispatchCapturePayPalOrderPaymentRequest(instrumentDeclined: true);

        self::assertSame(PaymentRequestInterface::STATE_FAILED, $paymentRequest->getState());
        self::assertSame(['reason' => 'PayPal declined the payment method. Choose another one.'], $paymentRequest->getResponseData());
        self::assertSame(PaymentInterface::STATE_NEW, $paymentRequest->getPayment()->getState());
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

    public function test_a_capture_waits_while_another_process_holds_the_payment_request(): void
    {
        $paymentRequest = $this->processingCapturePaymentRequest();
        $parallelConnection = $this->parallelConnection();
        $parallelConnection->beginTransaction();
        $parallelConnection->executeQuery(
            'SELECT state FROM sylius_payment_request WHERE hash = ? FOR UPDATE',
            [$paymentRequest->getHash()],
            [UuidType::NAME],
        );
        $this->paymentRequestManager()->getConnection()->executeStatement($this->lockTimeoutStatement());

        try {
            self::getContainer()->get('sylius.announcer.payment_request')->dispatchPaymentRequestCommand($paymentRequest);
            self::fail('A capture of a payment request another process holds must wait for it.');
        } catch (HandlerFailedException $exception) {
            $driverException = $exception->getPrevious();
            self::assertInstanceOf(DriverException::class, $driverException);
            self::assertTrue($driverException instanceof LockWaitTimeoutException || '55P03' === $driverException->getSQLState());
        } finally {
            $parallelConnection->rollBack();
            $parallelConnection->close();
        }
    }

    public function test_a_capture_leaves_a_payment_request_another_process_completed_meanwhile_alone(): void
    {
        $hash = $this->processingCapturePaymentRequest()->getId();
        /** @var PaymentRequestInterface $paymentRequest */
        $paymentRequest = self::getContainer()->get('sylius.repository.payment_request')->find($hash);
        $parallelConnection = $this->parallelConnection();
        $parallelConnection->executeStatement(
            'UPDATE sylius_payment_request SET state = ? WHERE hash = ?',
            [PaymentRequestInterface::STATE_COMPLETED, $paymentRequest->getHash()],
            [ParameterType::STRING, UuidType::NAME],
        );
        $parallelConnection->close();

        self::getContainer()->get('sylius.announcer.payment_request')->dispatchPaymentRequestCommand($paymentRequest);

        $this->paymentRequestManager()->clear();
        /** @var PaymentRequestInterface $reloaded */
        $reloaded = self::getContainer()->get('sylius.repository.payment_request')->find($hash);
        self::assertSame(PaymentRequestInterface::STATE_COMPLETED, $reloaded->getState());
        self::assertNotSame(PaymentInterface::STATE_COMPLETED, $reloaded->getPayment()->getState());
    }

    private function dispatchCapturePayPalOrderPaymentRequest(
        string $captureStatus = 'COMPLETED',
        ?string $capturedValue = null,
        bool $approved = true,
        bool $paypalFailsFirst = false,
        bool $instrumentDeclined = false,
    ): PaymentRequestInterface {
        $paymentRequest = $this->processingCapturePaymentRequest();
        /** @var OrderInterface $order */
        $order = $paymentRequest->getPayment()->getOrder();
        $entityManager = $this->getEntityManager();

        if ($approved || $instrumentDeclined) {
            $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID');
            $this->payPalApi()->mockUpdateOrderAddress();
        }
        if ($instrumentDeclined) {
            $this->payPalApi()->mockCaptureOfDeclinedInstrument(requestId: sprintf('%s-capture', (string) $paymentRequest->getId()));
        } elseif ($approved) {
            $requestId = sprintf('%s-capture', (string) $paymentRequest->getId());
            if ($paypalFailsFirst) {
                $this->payPalApi()->mockCaptureServerError(requestId: $requestId);
            }
            $this->payPalApi()->mockCapture(requestId: $requestId);
            $this->payPalApi()->mockOrderDetailsWithCapture(
                captureStatus: $captureStatus,
                value: $capturedValue ?? number_format($order->getTotal() / 100, 2, '.', ''),
            );
        } else {
            $this->payPalApi()->mockUnapprovedOrderDetails();
        }

        self::getContainer()->get('sylius.announcer.payment_request')->dispatchPaymentRequestCommand($paymentRequest);

        $hash = $paymentRequest->getId();
        $entityManager->clear();

        /** @var PaymentRequestInterface $reloaded */
        $reloaded = self::getContainer()->get('sylius.repository.payment_request')->find($hash);

        return $reloaded;
    }

    private function processingCapturePaymentRequest(): PaymentRequestInterface
    {
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

        return $paymentRequest;
    }

    private function parallelConnection(): Connection
    {
        $connection = $this->getEntityManager()->getConnection();
        $parallelConnection = DriverManager::getConnection($connection->getParams(), $connection->getConfiguration());
        $parallelConnection->executeStatement($this->lockTimeoutStatement());

        return $parallelConnection;
    }

    private function paymentRequestManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $paymentRequestManager */
        $paymentRequestManager = self::getContainer()->get('sylius.manager.payment_request');

        return $paymentRequestManager;
    }

    private function lockTimeoutStatement(): string
    {
        return $this->getEntityManager()->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform
            ? "SET lock_timeout = '1s'"
            : 'SET SESSION innodb_lock_wait_timeout = 1';
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
