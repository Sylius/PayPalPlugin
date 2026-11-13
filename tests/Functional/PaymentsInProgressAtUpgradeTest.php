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

    public function test_it_captures_the_paypal_order_the_buyer_approved_after_the_upgrade(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId();
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->preUpgradeOrder());
        $this->payPalApi()->mockCapture(requestId: 'sylius-paypal-pre-upgrade-PAYPAL_ORDER_ID');
        $this->payPalApi()->mockOrderDetailsWithCapture();

        self::assertSame(Command::SUCCESS, $this->settlePayments());

        $payment = $this->reloadedPayment($paymentId);
        self::assertSame(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertSame('CAPTURE_ID', $payment->getDetails()['transaction_id']);
        $this->assertPayPalApiExpectationsWereMet();
    }

    public function test_it_authorizes_the_paypal_order_the_buyer_approved_after_the_upgrade_when_it_was_created_to_be_authorized(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId();
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->preUpgradeOrder(['intent' => 'AUTHORIZE']));
        $this->payPalApi()->mockAuthorize(requestId: 'sylius-paypal-pre-upgrade-PAYPAL_ORDER_ID');
        $this->payPalApi()->mockOrderDetailsWithAuthorization();

        self::assertSame(Command::SUCCESS, $this->settlePayments());

        self::assertSame(PaymentInterface::STATE_AUTHORIZED, $this->reloadedPayment($paymentId)->getState());
        $this->assertPayPalApiExpectationsWereMet();
    }

    public function test_it_lets_the_buyer_pay_again_once_paypal_declines_the_payment_method_they_approved_before_the_upgrade(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId();
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->preUpgradeOrder());
        $this->payPalApi()->mockCaptureOfDeclinedInstrument(requestId: 'sylius-paypal-pre-upgrade-PAYPAL_ORDER_ID');

        self::assertSame(Command::SUCCESS, $this->settlePayments());

        $payment = $this->reloadedPayment($paymentId);
        self::assertSame(PaymentInterface::STATE_FAILED, $payment->getState());
        self::assertSame(PaymentInterface::STATE_NEW, $payment->getOrder()?->getLastPayment()?->getState());
    }

    public function test_it_captures_the_card_order_of_a_payment_created_on_2_1_which_stored_no_payment_source(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId();
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->preUpgradeOrder([
            'status' => 'CREATED',
            'payment_source' => ['card' => ['last_digits' => '1111']],
        ]));
        $this->payPalApi()->mockCapture(requestId: 'sylius-paypal-pre-upgrade-PAYPAL_ORDER_ID');
        $this->payPalApi()->mockOrderDetailsWithCapture();

        self::assertSame(Command::SUCCESS, $this->settlePayments());

        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadedPayment($paymentId)->getState());
        $this->assertPayPalApiExpectationsWereMet();
    }

    public function test_it_keeps_waiting_for_the_buyer_still_at_paypal_after_the_upgrade(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId();
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->preUpgradeOrder(['status' => 'CREATED']));

        self::assertSame(Command::SUCCESS, $this->settlePayments());

        self::assertSame(PaymentInterface::STATE_PROCESSING, $this->reloadedPayment($paymentId)->getState());
    }

    public function test_it_lets_the_buyer_pay_again_once_paypal_no_longer_returns_the_order_they_left_before_the_upgrade(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId();
        $this->lastChangePaymentAt($paymentId, '-4 days');
        $this->payPalApi()->mockOrderDetailsNotFound();

        self::assertSame(Command::SUCCESS, $this->settlePayments());

        $payment = $this->reloadedPayment($paymentId);
        self::assertSame(PaymentInterface::STATE_CANCELLED, $payment->getState());
        self::assertSame(PaymentInterface::STATE_NEW, $payment->getOrder()?->getLastPayment()?->getState());
        self::assertSame(Command::SUCCESS, $this->settlePayments());
    }

    public function test_it_reports_paypal_not_returning_an_order_the_buyer_left_before_the_upgrade_while_paypal_may_still_hold_it(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId();
        $this->payPalApi()->mockOrderDetailsNotFound();

        self::assertSame(Command::FAILURE, $this->settlePayments());

        self::assertSame(PaymentInterface::STATE_PROCESSING, $this->reloadedPayment($paymentId)->getState());
    }

    public function test_it_lets_the_buyer_pay_again_once_paypal_voided_the_order_they_left_before_the_upgrade(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId();
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->preUpgradeOrder(['status' => 'VOIDED']));

        self::assertSame(Command::SUCCESS, $this->settlePayments());

        $payment = $this->reloadedPayment($paymentId);
        self::assertSame(PaymentInterface::STATE_CANCELLED, $payment->getState());
        self::assertSame(PaymentInterface::STATE_NEW, $payment->getOrder()?->getLastPayment()?->getState());
    }

    public function test_it_captures_the_paypal_order_the_buyer_approved_after_the_upgrade_once_paypal_notifies_about_the_approval(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId();
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->preUpgradeOrder());
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->preUpgradeOrder());
        $this->payPalApi()->mockCapture(requestId: 'sylius-paypal-pre-upgrade-PAYPAL_ORDER_ID');
        $this->payPalApi()->mockOrderDetailsWithCapture();

        $this->client->request('POST', '/payment-methods/PAYPAL', content: (string) json_encode([
            'id' => 'WH-1',
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource_type' => 'checkout-order',
            'resource' => ['id' => 'PAYPAL_ORDER_ID', 'status' => 'APPROVED'],
        ]));

        self::assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadedPayment($paymentId)->getState());
        $this->assertPayPalApiExpectationsWereMet();
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

    private function lastChangePaymentAt(mixed $paymentId, string $modifier): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE sylius_payment SET updated_at = ? WHERE id = ?',
            [(new \DateTimeImmutable($modifier))->format('Y-m-d H:i:s'), $paymentId],
        );
    }

    private function paymentWaitingForTheBuyerAtPayPalId(): mixed
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/processing_paypal_order.yaml']);
        /** @var PaymentInterface $payment */
        $payment = $fixtures['processing_paypal_payment'];
        $payment->setDetails(['status' => 'CAPTURED', 'paypal_order_id' => 'PAYPAL_ORDER_ID', 'reference_id' => 'REFERENCE_ID', 'payment_amount' => 20]);
        $payment->getOrder()?->getItems()->first()->setUnitPrice(20);
        $this->getEntityManager()->flush();

        return $payment->getId();
    }

    /**
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    private function preUpgradeOrder(array $order = []): array
    {
        return array_merge([
            'status' => 'APPROVED',
            'intent' => 'CAPTURE',
            'purchase_units' => [['reference_id' => 'REFERENCE_ID', 'custom_id' => '000000001']],
        ], $order);
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
