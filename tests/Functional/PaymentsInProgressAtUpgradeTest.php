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
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Response;

final class PaymentsInProgressAtUpgradeTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    public function test_it_completes_a_payment_whose_capture_payum_left_pending_once_paypal_completes_it(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/processing_paypal_order.yaml']);
        $paymentId = $fixtures['processing_paypal_payment']->getId();
        $this->payPalApi()->mockOrderDetailsWithCapture();

        self::assertSame(Command::SUCCESS, $this->settlePayments());

        $payment = $this->reloadedPayment($paymentId);
        self::assertSame(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertSame('COMPLETED', $payment->getDetails()['status']);
    }

    public function test_it_pays_a_payment_payum_left_waiting_for_the_payer_with_a_new_paypal_order(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/new_order.yaml']);
        /** @var PaymentInterface $payment */
        $payment = $fixtures['paypal_payment'];
        $payment->setDetails(['status' => 'CREATED', 'paypal_order_id' => 'PAYUM_PAYPAL_ORDER_ID', 'reference_id' => 'REFERENCE_ID', 'payment_amount' => 20]);
        $payment->getOrder()?->setBillingAddress($this->billingAddress());
        $hash = $this->capturePaymentRequestHash($payment);
        $this->payPalApi()->mockCreateOrder('PAYPAL_ORDER_ID');

        $this->client->request(
            'POST',
            sprintf('/en_US/paypal/payment-requests/%s/order', $hash),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['paymentSource' => 'paypal'], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $details = $this->reloadedPayment($payment->getId())->getDetails();
        self::assertSame('PAYPAL_ORDER_ID', $details['paypal_order_id']);
        self::assertSame(['PAYUM_PAYPAL_ORDER_ID' => 'paypal'], $details['superseded_paypal_orders']);
    }

    public function test_it_completes_a_trustly_payment_payum_sent_to_the_bank_and_drops_its_payer_action(): void
    {
        $paymentId = $this->trustlyPaymentAtTheBankId();
        $this->payPalApi()->mockOrderDetailsWithCapture();

        self::assertSame(Command::SUCCESS, $this->settlePayments());

        $payment = $this->reloadedPayment($paymentId);
        self::assertSame(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertArrayNotHasKey('payer_action_url', $payment->getDetails());
        self::assertArrayNotHasKey('payer_action_return_nonce', $payment->getDetails());
        self::assertArrayNotHasKey('payer_action_cancel_nonce', $payment->getDetails());
    }

    public function test_it_keeps_a_trustly_payment_payum_sent_to_the_bank_waiting_while_the_payer_is_still_there(): void
    {
        $paymentId = $this->trustlyPaymentAtTheBankId();
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', ['status' => 'PAYER_ACTION_REQUIRED']);

        self::assertSame(Command::SUCCESS, $this->settlePayments());

        $payment = $this->reloadedPayment($paymentId);
        self::assertSame(PaymentInterface::STATE_PROCESSING, $payment->getState());
        self::assertSame('https://www.sandbox.paypal.com/payment/trustly?token=PAYPAL_ORDER_ID', $payment->getDetails()['payer_action_url']);
    }

    private function trustlyPaymentAtTheBankId(): mixed
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/redirect_paypal_order.yaml']);
        /** @var PaymentInterface $payment */
        $payment = $fixtures['redirect_paypal_payment'];
        $payment->setDetails(array_merge($payment->getDetails(), [
            'payer_action_return_nonce' => 'RETURN_NONCE',
            'payer_action_cancel_nonce' => 'CANCEL_NONCE',
        ]));
        $this->getEntityManager()->flush();

        return $payment->getId();
    }

    private function capturePaymentRequestHash(PaymentInterface $payment): string
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        $paymentRequest = self::getContainer()->get('sylius.factory.payment_request')->create($payment, $paymentMethod);
        $paymentRequest->setAction(PaymentRequestInterface::ACTION_CAPTURE);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($paymentRequest);
        $entityManager->flush();

        return (string) $paymentRequest->getId();
    }

    private function settlePayments(): int
    {
        return (new CommandTester((new Application(self::$kernel))->find('sylius-paypal:settle-payments')))->execute([]);
    }

    private function reloadedPayment(mixed $paymentId): PaymentInterface
    {
        $this->getEntityManager()->clear();

        /** @var PaymentInterface $payment */
        $payment = self::getContainer()->get('sylius.repository.payment')->find($paymentId);

        return $payment;
    }

    private function billingAddress(): AddressInterface
    {
        /** @var AddressInterface $address */
        $address = self::getContainer()->get('sylius.factory.address')->createNew();
        $address->setFirstName('Patrick');
        $address->setLastName('Watson');
        $address->setStreet('Damrak 1');
        $address->setCity('Amsterdam');
        $address->setPostcode('1012 LG');
        $address->setCountryCode('NL');

        return $address;
    }
}
