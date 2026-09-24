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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Sylius\PayPalPlugin\Processor\PayPalPaymentSettlementProcessor;

final class PayPalPaymentSettlementProcessorTest extends TestCase
{
    private CacheAuthorizeClientApiInterface&MockObject $authorizeClientApi;

    private OrderDetailsApiInterface&MockObject $orderDetailsApi;

    private StateMachineInterface&MockObject $stateMachine;

    private ObjectManager&MockObject $paymentManager;

    private LoggerInterface&MockObject $logger;

    private PaymentInterface&MockObject $payment;

    private PayPalPaymentSettlementProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorizeClientApi = $this->createMock(CacheAuthorizeClientApiInterface::class);
        $this->orderDetailsApi = $this->createMock(OrderDetailsApiInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->paymentManager = $this->createMock(ObjectManager::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->payment = $this->createMock(PaymentInterface::class);
        $this->payment->method('getId')->willReturn(7);
        $this->payment->method('getAmount')->willReturn(1539);
        $this->payment->method('getCurrencyCode')->willReturn('EUR');
        $this->payment->method('getMethod')->willReturn($this->createMock(PaymentMethodInterface::class));
        $this->payment->method('getDetails')->willReturn([
            'status' => 'CAPTURED',
            'paypal_order_id' => '5O190127TN364715T',
            'payment_source' => 'trustly',
        ]);

        $this->processor = new PayPalPaymentSettlementProcessor(
            $this->authorizeClientApi,
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

    public function test_it_reports_money_it_cannot_record_against_a_cancelled_payment(): void
    {
        $this->payment->method('getState')->willReturn(PaymentInterface::STATE_CANCELLED);
        $this->stateMachine->method('can')->willReturn(false);

        $this->logger->expects(self::once())->method('error');
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
        $this->authorizeClientApi->method('authorize')->willReturn('TOKEN');
        $this->orderDetailsApi
            ->expects(self::once())
            ->method('get')
            ->with('TOKEN', '5O190127TN364715T')
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
