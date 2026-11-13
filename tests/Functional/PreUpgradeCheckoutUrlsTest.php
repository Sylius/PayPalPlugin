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

final class PreUpgradeCheckoutUrlsTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    private const RETURN_NONCE = 'RETURN_NONCE_0123456789';

    private const CANCEL_NONCE = 'CANCEL_NONCE_0123456789';

    public function test_it_sends_the_buyer_from_the_paypal_payment_page_of_2_x_to_the_payment_page_of_the_order(): void
    {
        $this->paymentWaitingForTheBuyerAtPayPalId();

        $this->client->request('GET', '/en_US/pay-with-paypal/TOKEN/1');

        self::assertResponseRedirects('/en_US/order/TOKEN/pay');
    }

    public function test_it_lets_the_buyer_pay_again_after_closing_the_paypal_window_opened_before_the_upgrade(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId();

        $this->client->request(
            'POST',
            '/en_US/cancel-pay-pal-checkout-payment',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['payPalOrderId' => 'PAYPAL_ORDER_ID']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $payment = $this->reloadedPayment($paymentId);
        self::assertSame(PaymentInterface::STATE_CANCELLED, $payment->getState());
        self::assertSame(PaymentInterface::STATE_NEW, $payment->getOrder()?->getLastPayment()?->getState());
    }

    public function test_it_ignores_closing_the_paypal_window_of_an_unknown_paypal_order(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId();

        $this->client->request(
            'POST',
            '/en_US/cancel-pay-pal-checkout-payment',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['payPalOrderId' => 'UNKNOWN_PAYPAL_ORDER_ID']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame(PaymentInterface::STATE_PROCESSING, $this->reloadedPayment($paymentId)->getState());
    }

    public function test_it_does_not_cancel_the_payment_of_a_buyer_at_the_bank_when_the_paypal_window_of_2_x_is_closed(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId([
            'payment_source' => 'trustly',
            'payer_action_url' => 'https://www.sandbox.paypal.com/payment/trustly?token=PAYPAL_ORDER_ID',
        ]);

        $this->client->request(
            'POST',
            '/en_US/cancel-pay-pal-checkout-payment',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['payPalOrderId' => 'PAYPAL_ORDER_ID']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame(PaymentInterface::STATE_PROCESSING, $this->reloadedPayment($paymentId)->getState());
    }

    public function test_it_captures_the_paypal_order_the_buyer_approved_in_the_paypal_window_opened_before_the_upgrade(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId();
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->preUpgradeOrder());
        $this->payPalApi()->mockCapture(requestId: 'sylius-paypal-pre-upgrade-PAYPAL_ORDER_ID');
        $this->payPalApi()->mockOrderDetailsWithCapture();

        $this->client->request(
            'POST',
            '/en_US/complete-pay-pal-order/TOKEN',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['payPalOrderId' => 'PAYPAL_ORDER_ID']),
        );

        self::assertResponseIsSuccessful();
        $response = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(PaymentInterface::STATE_COMPLETED, $response['status']);
        self::assertSame('PAYPAL_ORDER_ID', $response['orderId']);
        self::assertStringEndsWith('/en_US/order/TOKEN', $response['return_url']);
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadedPayment($paymentId)->getState());
        $this->assertPayPalApiExpectationsWereMet();
    }

    public function test_it_fails_the_payment_and_tells_the_buyer_why_when_the_card_was_refused_in_the_paypal_window_opened_before_the_upgrade(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId(['payment_source' => 'card']);
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->preUpgradeOrder([
            'payment_source' => ['card' => ['authentication_result' => [
                'liability_shift' => 'NO',
                'three_d_secure' => ['enrollment_status' => 'Y', 'authentication_status' => 'N'],
            ]]],
        ]));

        $this->client->request(
            'POST',
            '/en_US/complete-pay-pal-order/TOKEN',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['payPalOrderId' => 'PAYPAL_ORDER_ID']),
        );

        self::assertResponseIsSuccessful();
        $response = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(PaymentInterface::STATE_FAILED, $response['status']);
        self::assertStringEndsWith('/en_US/order/TOKEN', $response['return_url']);
        self::assertSame(['sylius_paypal.three_d_secure_declined'], $this->client->getRequest()->getSession()->getFlashBag()->peek('error'));
        $payment = $this->reloadedPayment($paymentId);
        self::assertSame(PaymentInterface::STATE_FAILED, $payment->getState());
        self::assertSame(PaymentInterface::STATE_NEW, $payment->getOrder()?->getLastPayment()?->getState());
    }

    public function test_it_lets_the_buyer_retry_the_card_authentication_that_did_not_finish_in_the_paypal_window_opened_before_the_upgrade(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId(['payment_source' => 'card']);
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->preUpgradeOrder([
            'payment_source' => ['card' => ['authentication_result' => [
                'liability_shift' => 'UNKNOWN',
                'three_d_secure' => ['enrollment_status' => 'Y', 'authentication_status' => 'U'],
            ]]],
        ]));

        $this->client->request(
            'POST',
            '/en_US/complete-pay-pal-order/TOKEN',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['payPalOrderId' => 'PAYPAL_ORDER_ID']),
        );

        self::assertResponseIsSuccessful();
        $response = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(PaymentInterface::STATE_CANCELLED, $response['status']);
        self::assertStringEndsWith('/en_US/order/TOKEN/pay', $response['return_url']);
        self::assertSame(['sylius_paypal.three_d_secure_retry'], $this->client->getRequest()->getSession()->getFlashBag()->peek('error'));
        self::assertSame(PaymentInterface::STATE_NEW, $this->reloadedPayment($paymentId)->getOrder()?->getLastPayment()?->getState());
    }

    public function test_it_leaves_capturing_an_approved_transfer_started_before_the_upgrade_to_paypal(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId(['payment_source' => 'trustly']);
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->preUpgradeOrder());

        $this->client->request('GET', sprintf('/en_US/paypal/redirect-return/TOKEN/%s', self::RETURN_NONCE));

        self::assertResponseRedirects('/en_US/order/TOKEN');
        self::assertSame(PaymentInterface::STATE_PROCESSING, $this->reloadedPayment($paymentId)->getState());
        $this->assertPayPalApiExpectationsWereMet();
    }

    public function test_it_refuses_to_complete_another_paypal_order_than_the_one_of_the_payment(): void
    {
        $this->paymentWaitingForTheBuyerAtPayPalId();

        $this->client->request(
            'POST',
            '/en_US/complete-pay-pal-order/TOKEN',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['payPalOrderId' => 'ANOTHER_PAYPAL_ORDER_ID']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_it_sends_the_buyer_back_from_the_bank_to_the_order_once_the_transfer_started_before_the_upgrade_is_paid(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId();
        $this->payPalApi()->mockOrderDetailsWithCapture();

        $this->client->request('GET', sprintf('/en_US/paypal/redirect-return/TOKEN/%s', self::RETURN_NONCE));

        self::assertResponseRedirects('/en_US/order/TOKEN');
        self::assertSame(PaymentInterface::STATE_COMPLETED, $this->reloadedPayment($paymentId)->getState());
        $this->assertPayPalApiExpectationsWereMet();
    }

    public function test_it_sends_the_buyer_back_from_the_bank_to_the_order_while_the_transfer_started_before_the_upgrade_is_on_its_way(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId();
        $this->payPalApi()->mockOrderDetailsWithCapture(captureStatus: 'PENDING');

        $this->client->request('GET', sprintf('/en_US/paypal/redirect-return/TOKEN/%s', self::RETURN_NONCE));

        self::assertResponseRedirects('/en_US/order/TOKEN');
        self::assertSame(PaymentInterface::STATE_PROCESSING, $this->reloadedPayment($paymentId)->getState());
    }

    public function test_it_does_not_ask_paypal_about_a_return_from_the_bank_carrying_another_nonce(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId();

        $this->client->request('GET', '/en_US/paypal/redirect-return/TOKEN/ANOTHER_NONCE_0123456789');

        self::assertResponseRedirects('/en_US/order/TOKEN');
        self::assertSame(PaymentInterface::STATE_PROCESSING, $this->reloadedPayment($paymentId)->getState());
    }

    public function test_it_lets_the_buyer_pay_again_after_cancelling_at_the_bank_the_transfer_started_before_the_upgrade(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId();
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->preUpgradeOrder(['status' => 'PAYER_ACTION_REQUIRED']));

        $this->client->request('GET', sprintf('/en_US/paypal/redirect-cancel/TOKEN/%s', self::CANCEL_NONCE));

        self::assertResponseRedirects('/en_US/order/TOKEN/pay');
        $payment = $this->reloadedPayment($paymentId);
        self::assertSame(PaymentInterface::STATE_CANCELLED, $payment->getState());
        self::assertSame(PaymentInterface::STATE_NEW, $payment->getOrder()?->getLastPayment()?->getState());
    }

    public function test_it_fails_the_payment_the_bank_refused_for_a_transfer_started_before_the_upgrade(): void
    {
        $paymentId = $this->paymentWaitingForTheBuyerAtPayPalId();
        $this->payPalApi()->mockOrderDetails('PAYPAL_ORDER_ID', $this->preUpgradeOrder(['status' => 'PAYER_ACTION_REQUIRED']));

        $this->client->request('GET', sprintf('/en_US/paypal/redirect-cancel/TOKEN/%s?errorcode=processing_error', self::CANCEL_NONCE));

        self::assertResponseRedirects('/en_US/order/TOKEN/pay');
        $payment = $this->reloadedPayment($paymentId);
        self::assertSame(PaymentInterface::STATE_FAILED, $payment->getState());
        self::assertSame(PaymentInterface::STATE_NEW, $payment->getOrder()?->getLastPayment()?->getState());
    }

    /** @param array<string, mixed> $details */
    private function paymentWaitingForTheBuyerAtPayPalId(array $details = []): mixed
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/processing_paypal_order.yaml']);
        /** @var PaymentInterface $payment */
        $payment = $fixtures['processing_paypal_payment'];
        $payment->setDetails([
            'status' => 'CAPTURED',
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'reference_id' => 'REFERENCE_ID',
            'payment_amount' => 20,
            'payer_action_return_nonce' => self::RETURN_NONCE,
            'payer_action_cancel_nonce' => self::CANCEL_NONCE,
        ] + $details);
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

    private function reloadedPayment(mixed $paymentId): PaymentInterface
    {
        $this->getEntityManager()->clear();

        /** @var PaymentInterface $payment */
        $payment = self::getContainer()->get('sylius.repository.payment')->find($paymentId);

        return $payment;
    }
}
