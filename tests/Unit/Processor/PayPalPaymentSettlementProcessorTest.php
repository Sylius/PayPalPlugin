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

namespace Tests\Sylius\PayPalPlugin\Unit\Processor;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Sylius\PayPalPlugin\Processor\PayPalPaymentSettlementProcessor;

final class PayPalPaymentSettlementProcessorTest extends TestCase
{
    private OrderDetailsApiInterface&MockObject $orderDetailsApi;

    private StateMachineInterface&MockObject $stateMachine;

    private ObjectManager&MockObject $paymentManager;

    private LoggerInterface&MockObject $logger;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private PaymentInterface&MockObject $payment;

    private PayPalPaymentSettlementProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderDetailsApi = $this->createMock(OrderDetailsApiInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->paymentManager = $this->createMock(ObjectManager::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $this->payment = $this->createMock(PaymentInterface::class);
        $this->payment->method('getId')->willReturn(7);
        $this->payment->method('getAmount')->willReturn(1539);
        $this->payment->method('getCurrencyCode')->willReturn('EUR');
        $this->payment->method('getMethod')->willReturn($this->paymentMethod);
        $this->payment->method('getDetails')->willReturn([
            'status' => 'CAPTURED',
            'paypal_order_id' => '5O190127TN364715T',
            'payment_source' => 'trustly',
        ]);

        $this->processor = new PayPalPaymentSettlementProcessor(
            $this->orderDetailsApi,
            $this->stateMachine,
            $this->paymentManager,
            $this->logger,
        );
    }

    public function test_it_implements_payment_settlement_processor_interface(): void
    {
        self::assertInstanceOf(PaymentSettlementProcessorInterface::class, $this->processor);
    }

    public function test_it_leaves_the_payment_processing_when_the_order_is_completed_but_the_capture_is_pending(): void
    {
        $this->payment->method('getState')->willReturn(PaymentInterface::STATE_PROCESSING);

        $this->stateMachine->expects(self::never())->method('apply');
        $this->payment->expects(self::never())->method('setDetails');

        $this->processor->settle($this->payment, $this->orderDetails('PENDING'));
    }

    public function test_it_completes_the_payment_once_the_capture_is_completed(): void
    {
        $this->payment->method('getState')->willReturn(PaymentInterface::STATE_PROCESSING);
        $this->stateMachine->method('can')->willReturn(true);

        $this->payment->expects(self::once())->method('setDetails')->with([
            'status' => 'COMPLETED',
            'paypal_order_id' => '5O190127TN364715T',
            'payment_source' => 'trustly',
            'transaction_id' => '892032536L382192T',
        ]);
        $this->stateMachine
            ->expects(self::once())
            ->method('apply')
            ->with($this->payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE)
        ;
        $this->paymentManager->expects(self::once())->method('flush');

        $this->processor->settle($this->payment, $this->orderDetails('COMPLETED'));
    }

    public function test_it_records_the_captured_authorization_before_asking_whether_the_payment_can_be_completed(): void
    {
        $this->payment->method('getState')->willReturn(PaymentInterface::STATE_AUTHORIZED);
        $calls = [];
        $this->payment->method('setDetails')->willReturnCallback(function (array $details) use (&$calls): void {
            $calls[] = ['setDetails', $details['authorization_status'] ?? null];
        });
        $this->stateMachine->method('can')->willReturnCallback(function () use (&$calls): bool {
            $calls[] = ['can', null];

            return true;
        });

        $orderDetails = $this->orderDetails('COMPLETED');
        $orderDetails['purchase_units'][0]['payments']['authorizations'] = [['id' => 'AUTHORIZATION_ID', 'status' => 'CAPTURED']];

        $this->processor->settle($this->payment, $orderDetails);

        self::assertSame([['setDetails', 'CAPTURED'], ['can', null], ['setDetails', 'CAPTURED']], $calls);
    }

    public function test_it_fails_the_payment_once_the_capture_is_declined(): void
    {
        $this->payment->method('getState')->willReturn(PaymentInterface::STATE_PROCESSING);
        $this->stateMachine->method('can')->willReturn(true);

        $this->stateMachine
            ->expects(self::once())
            ->method('apply')
            ->with($this->payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL)
        ;

        $this->processor->settle($this->payment, $this->orderDetails('DECLINED'));
    }

    public function test_it_changes_nothing_when_the_same_event_is_delivered_again(): void
    {
        $this->payment->method('getState')->willReturn(PaymentInterface::STATE_COMPLETED);

        $this->stateMachine->expects(self::never())->method('apply');
        $this->payment->expects(self::never())->method('setDetails');
        $this->logger->expects(self::never())->method('error');

        $this->processor->settle($this->payment, $this->orderDetails('COMPLETED'));
    }

    public function test_it_keeps_a_capture_paypal_completed_for_a_cancelled_payment_to_be_refunded(): void
    {
        $this->assertKeepsLateCapture(PaymentInterface::STATE_CANCELLED);
    }

    public function test_it_keeps_a_capture_paypal_completed_for_a_failed_payment_to_be_refunded(): void
    {
        $this->assertKeepsLateCapture(PaymentInterface::STATE_FAILED);
    }

    public function test_it_keeps_the_same_late_capture_only_once(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_CANCELLED);
        $payment->method('getDetails')->willReturn([
            'paypal_order_id' => '5O190127TN364715T',
            PayPalPaymentSettlementProcessor::LATE_CAPTURE => ['id' => '892032536L382192T', 'amount' => 1539, 'currency_code' => 'EUR'],
        ]);
        $this->stateMachine->method('can')->willReturn(false);

        $payment->expects(self::never())->method('setDetails');
        $this->paymentManager->expects(self::never())->method('flush');
        $this->logger->expects(self::never())->method('error');
        $this->stateMachine->expects(self::never())->method('apply');

        $this->processor->settle($payment, $this->orderDetails('COMPLETED'));
    }

    #[DataProvider('refusedCaptureStatuses')]
    public function test_it_ignores_a_refused_capture_for_an_attempt_the_payer_cancelled(string $status): void
    {
        $this->payment->method('getState')->willReturn(PaymentInterface::STATE_CANCELLED);
        $this->stateMachine->method('can')->willReturn(false);

        $this->logger->expects(self::never())->method('error');
        $this->payment->expects(self::never())->method('setDetails');
        $this->paymentManager->expects(self::never())->method('flush');
        $this->stateMachine->expects(self::never())->method('apply');

        $this->processor->settle($this->payment, $this->orderDetails($status));
    }

    #[DataProvider('refusedCaptureStatuses')]
    public function test_it_reports_a_refused_capture_for_a_completed_payment(string $status): void
    {
        $this->payment->method('getState')->willReturn(PaymentInterface::STATE_COMPLETED);
        $this->stateMachine->method('can')->willReturn(false);

        $this->logger->expects(self::once())->method('error')->with(self::stringContains('Reconcile it by hand.'));
        $this->payment->expects(self::never())->method('setDetails');
        $this->paymentManager->expects(self::never())->method('flush');
        $this->stateMachine->expects(self::never())->method('apply');

        $this->processor->settle($this->payment, $this->orderDetails($status));
    }

    public function test_it_pays_an_unpaid_payment_with_the_paypal_order_another_attempt_superseded(): void
    {
        $payment = $this->paymentWithSupersededPayPalOrder(PaymentInterface::STATE_NEW);
        $this->stateMachine->method('can')->willReturn(true);

        $payment->expects(self::once())->method('setDetails')->with([
            'status' => 'COMPLETED',
            'paypal_order_id' => '5O190127TN364715T',
            'payment_source' => 'trustly',
            'superseded_paypal_orders' => ['8XN46231DT201694L' => 'paypal'],
            'transaction_id' => '892032536L382192T',
        ]);
        $this->stateMachine->expects(self::once())->method('apply')->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);

        $this->processor->settle($payment, $this->orderDetails('COMPLETED'), '5O190127TN364715T');
    }

    public function test_it_keeps_the_capture_of_a_superseded_paypal_order_on_a_paid_payment_to_be_refunded(): void
    {
        $payment = $this->paymentWithSupersededPayPalOrder(PaymentInterface::STATE_COMPLETED);
        $this->stateMachine->method('can')->willReturn(false);

        $payment->expects(self::once())->method('setDetails')->with(self::callback(static fn (array $details): bool => [
            'id' => '892032536L382192T',
            'order_id' => '5O190127TN364715T',
            'amount' => 1539,
            'currency_code' => 'EUR',
        ] === $details[PayPalPaymentSettlementProcessor::LATE_CAPTURE] && '8XN46231DT201694L' === $details['paypal_order_id']));
        $this->stateMachine->expects(self::never())->method('apply');

        $this->processor->settle($payment, $this->orderDetails('COMPLETED'), '5O190127TN364715T');
    }

    #[DataProvider('refusedCaptureStatuses')]
    public function test_it_ignores_a_refused_capture_of_a_superseded_paypal_order(string $status): void
    {
        $payment = $this->paymentWithSupersededPayPalOrder(PaymentInterface::STATE_NEW);
        $this->stateMachine->method('can')->willReturn(true);

        $payment->expects(self::never())->method('setDetails');
        $this->stateMachine->expects(self::never())->method('apply');

        $this->processor->settle($payment, $this->orderDetails($status), '5O190127TN364715T');
    }

    /** @return iterable<string, array{string}> */
    public static function refusedCaptureStatuses(): iterable
    {
        yield 'declined' => ['DECLINED'];
        yield 'failed' => ['FAILED'];
    }

    private function assertKeepsLateCapture(string $state): void
    {
        $this->payment->method('getState')->willReturn($state);
        $this->stateMachine->method('can')->willReturn(false);

        $this->payment->expects(self::once())->method('setDetails')->with([
            'status' => 'CAPTURED',
            'paypal_order_id' => '5O190127TN364715T',
            'payment_source' => 'trustly',
            PayPalPaymentSettlementProcessor::LATE_CAPTURE => ['id' => '892032536L382192T', 'order_id' => '5O190127TN364715T', 'amount' => 1539, 'currency_code' => 'EUR'],
        ]);
        $this->paymentManager->expects(self::once())->method('flush');
        $this->logger->expects(self::once())->method('error')->with(self::stringContains('kept on the payment to be refunded from the admin panel'));
        $this->stateMachine->expects(self::never())->method('apply');

        $this->processor->settle($this->payment, $this->orderDetails('COMPLETED'));
    }

    public function test_it_reports_a_capture_that_does_not_match_the_payment_but_still_completes_it(): void
    {
        $this->payment->method('getState')->willReturn(PaymentInterface::STATE_PROCESSING);
        $this->stateMachine->method('can')->willReturn(true);

        $this->logger->expects(self::once())->method('error');
        $this->stateMachine->expects(self::once())->method('apply');

        $this->processor->settle($this->payment, $this->orderDetails('COMPLETED', '99.00'));
    }

    public function test_it_records_a_capture_that_does_not_match_the_payment_on_the_payment(): void
    {
        $this->payment->method('getState')->willReturn(PaymentInterface::STATE_PROCESSING);
        $this->stateMachine->method('can')->willReturn(true);

        $this->payment->expects(self::once())->method('setDetails')->with([
            'status' => 'COMPLETED',
            'paypal_order_id' => '5O190127TN364715T',
            'payment_source' => 'trustly',
            'transaction_id' => '892032536L382192T',
            'captured_amount' => 9900,
            'captured_currency_code' => 'EUR',
        ]);

        $this->processor->settle($this->payment, $this->orderDetails('COMPLETED', '99.00'));
    }

    public function test_it_records_nothing_extra_when_the_capture_matches_the_payment(): void
    {
        $this->payment->method('getState')->willReturn(PaymentInterface::STATE_PROCESSING);
        $this->stateMachine->method('can')->willReturn(true);

        $this->payment
            ->expects(self::once())
            ->method('setDetails')
            ->willReturnCallback(function (array $details): void {
                self::assertArrayNotHasKey('captured_amount', $details);
                self::assertArrayNotHasKey('captured_currency_code', $details);
            })
        ;

        $this->processor->settle($this->payment, $this->orderDetails('COMPLETED'));
    }

    public function test_it_forgets_the_payer_action_of_an_attempt_that_is_over(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_PROCESSING);
        $payment->method('getAmount')->willReturn(1539);
        $payment->method('getCurrencyCode')->willReturn('EUR');
        $payment->method('getDetails')->willReturn([
            'paypal_order_id' => '5O190127TN364715T',
            'payment_source' => 'trustly',
            'payer_action_url' => 'https://www.sandbox.paypal.com/payment/trustly?token=X',
            'payer_action_return_nonce' => 'RETURN_NONCE',
            'payer_action_cancel_nonce' => 'CANCEL_NONCE',
        ]);
        $this->stateMachine->method('can')->willReturn(true);

        $payment
            ->expects(self::once())
            ->method('setDetails')
            ->willReturnCallback(function (array $details): void {
                self::assertArrayNotHasKey('payer_action_url', $details);
                self::assertArrayNotHasKey('payer_action_return_nonce', $details);
                self::assertArrayNotHasKey('payer_action_cancel_nonce', $details);
                self::assertSame('trustly', $details['payment_source']);
            })
        ;

        $this->processor->settle($payment, $this->orderDetails('COMPLETED'));
    }

    public function test_it_reads_the_order_from_paypal_when_it_is_given_none(): void
    {
        $this->payment->method('getState')->willReturn(PaymentInterface::STATE_PROCESSING);
        $this->orderDetailsApi
            ->expects(self::once())
            ->method('get')
            ->with($this->paymentMethod, '5O190127TN364715T')
            ->willReturn($this->orderDetails('PENDING'))
        ;

        $this->processor->settle($this->payment);
    }

    public function test_it_skips_a_payment_that_never_reached_paypal(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn([]);

        $this->logger->expects(self::once())->method('warning');
        $this->orderDetailsApi->expects(self::never())->method('get');

        $this->processor->settle($payment);
    }

    private function paymentWithSupersededPayPalOrder(string $state): PaymentInterface&MockObject
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getId')->willReturn(7);
        $payment->method('getState')->willReturn($state);
        $payment->method('getAmount')->willReturn(1539);
        $payment->method('getCurrencyCode')->willReturn('EUR');
        $payment->method('getDetails')->willReturn([
            'status' => 'CAPTURED',
            'paypal_order_id' => '8XN46231DT201694L',
            'reference_id' => 'REFERENCE_ID',
            'payment_source' => 'paypal',
            'superseded_paypal_orders' => ['5O190127TN364715T' => 'trustly'],
        ]);

        return $payment;
    }

    /** @return array<string, mixed> */
    private function orderDetails(string $captureStatus, string $value = '15.39'): array
    {
        return [
            'id' => '5O190127TN364715T',
            'status' => 'COMPLETED',
            'purchase_units' => [
                [
                    'reference_id' => 'REFERENCE_ID',
                    'payments' => [
                        'captures' => [
                            [
                                'id' => '892032536L382192T',
                                'status' => $captureStatus,
                                'amount' => ['currency_code' => 'EUR', 'value' => $value],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
