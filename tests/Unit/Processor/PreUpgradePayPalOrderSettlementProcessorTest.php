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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Api\AuthorizeOrderApiInterface;
use Sylius\PayPalPlugin\Api\CompleteOrderApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Checker\ApprovedPayPalOrderChecker;
use Sylius\PayPalPlugin\Checker\PreUpgradePayPalOrderCheckerInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiNotFoundException;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Exception\ThreeDSecureAuthenticationFailedException;
use Sylius\PayPalPlugin\Mapper\PayPalOrderDetailsMapper;
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Sylius\PayPalPlugin\Processor\PreUpgradePayPalOrderSettlementProcessor;
use Sylius\PayPalPlugin\Updater\ApprovedPayPalOrderUpdaterInterface;
use Sylius\PayPalPlugin\Verifier\ThreeDSecureVerifierInterface;
use Symfony\Component\Clock\MockClock;

final class PreUpgradePayPalOrderSettlementProcessorTest extends TestCase
{
    private const REQUEST_ID = 'sylius-paypal-pre-upgrade-PAYPAL_ORDER_ID';

    private PaymentSettlementProcessorInterface&MockObject $decoratedProcessor;

    private PreUpgradePayPalOrderCheckerInterface&MockObject $preUpgradePayPalOrderChecker;

    private OrderDetailsApiInterface&MockObject $orderDetailsApi;

    private CompleteOrderApiInterface&MockObject $completeOrderApi;

    private AuthorizeOrderApiInterface&MockObject $authorizeOrderApi;

    private ApprovedPayPalOrderUpdaterInterface&MockObject $approvedPayPalOrderUpdater;

    private ThreeDSecureVerifierInterface&MockObject $threeDSecureVerifier;

    private StateMachineInterface&MockObject $stateMachine;

    private EntityManagerInterface&MockObject $paymentManager;

    private Connection&MockObject $connection;

    private PaymentMethodInterface $paymentMethod;

    private PreUpgradePayPalOrderSettlementProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->decoratedProcessor = $this->createMock(PaymentSettlementProcessorInterface::class);
        $this->preUpgradePayPalOrderChecker = $this->createMock(PreUpgradePayPalOrderCheckerInterface::class);
        $this->orderDetailsApi = $this->createMock(OrderDetailsApiInterface::class);
        $this->completeOrderApi = $this->createMock(CompleteOrderApiInterface::class);
        $this->authorizeOrderApi = $this->createMock(AuthorizeOrderApiInterface::class);
        $this->approvedPayPalOrderUpdater = $this->createMock(ApprovedPayPalOrderUpdaterInterface::class);
        $this->threeDSecureVerifier = $this->createMock(ThreeDSecureVerifierInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->paymentManager = $this->createMock(EntityManagerInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->paymentManager->method('getConnection')->willReturn($this->connection);
        $this->paymentMethod = $this->createStub(PaymentMethodInterface::class);

        $this->processor = new PreUpgradePayPalOrderSettlementProcessor(
            $this->decoratedProcessor,
            $this->preUpgradePayPalOrderChecker,
            new ApprovedPayPalOrderChecker(),
            $this->approvedPayPalOrderUpdater,
            $this->orderDetailsApi,
            $this->completeOrderApi,
            $this->authorizeOrderApi,
            $this->threeDSecureVerifier,
            $this->stateMachine,
            $this->paymentManager,
            new MockClock('2026-11-12 12:00:00'),
            $this->createStub(LoggerInterface::class),
        );
    }

    public function test_it_captures_an_approved_order_the_payment_awaits_since_before_the_upgrade_and_settles_it(): void
    {
        $payment = $this->payment();
        $capturedOrder = $this->order(['status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [['id' => 'CAPTURE_ID', 'status' => 'COMPLETED']]]]]]);
        $this->orderDetailsApi
            ->method('get')
            ->with($this->paymentMethod, 'PAYPAL_ORDER_ID')
            ->willReturnOnConsecutiveCalls($this->order(['status' => 'APPROVED']), $capturedOrder)
        ;

        $this->approvedPayPalOrderUpdater->expects(self::once())->method('update')->with($payment);
        $this->completeOrderApi->expects(self::once())->method('complete')->with($this->paymentMethod, 'PAYPAL_ORDER_ID', self::REQUEST_ID);
        $this->authorizeOrderApi->expects(self::never())->method('authorize');
        $this->decoratedProcessor->expects(self::once())->method('settle')->with($payment, $capturedOrder, 'PAYPAL_ORDER_ID');

        $this->processor->settle($payment);
    }

    public function test_it_authorizes_an_approved_order_created_before_the_upgrade_to_be_authorized(): void
    {
        $payment = $this->payment();
        $this->orderDetailsApi->method('get')->willReturn($this->order(['status' => 'APPROVED', 'intent' => 'AUTHORIZE']));

        $this->authorizeOrderApi->expects(self::once())->method('authorize')->with($this->paymentMethod, 'PAYPAL_ORDER_ID', self::REQUEST_ID);
        $this->completeOrderApi->expects(self::never())->method('complete');
        $this->decoratedProcessor->expects(self::once())->method('settle');

        $this->processor->settle($payment);
    }

    public function test_it_settles_an_order_paypal_has_already_captured_when_asked_to_capture_it_again(): void
    {
        $payment = $this->payment();
        $this->orderDetailsApi->method('get')->willReturn($this->order(['status' => 'APPROVED']));
        $this->completeOrderApi
            ->method('complete')
            ->willThrowException(new PayPalApiRequestException('POST', 'v2/checkout/orders/PAYPAL_ORDER_ID/capture', 422, 'UNPROCESSABLE_ENTITY', issues: ['ORDER_ALREADY_CAPTURED']))
        ;

        $this->decoratedProcessor->expects(self::once())->method('settle');

        $this->processor->settle($payment);
    }

    public function test_it_fails_the_payment_when_paypal_declines_the_payment_method_of_the_buyer(): void
    {
        $payment = $this->payment();
        $this->orderDetailsApi->method('get')->willReturn($this->order(['status' => 'APPROVED']));
        $this->completeOrderApi
            ->method('complete')
            ->willThrowException(new PayPalApiRequestException('POST', 'v2/checkout/orders/PAYPAL_ORDER_ID/capture', 422, 'UNPROCESSABLE_ENTITY', issues: ['INSTRUMENT_DECLINED']))
        ;
        $this->stateMachine->method('can')->willReturn(true);

        $this->stateMachine->expects(self::once())->method('apply')->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL);
        $this->decoratedProcessor->expects(self::never())->method('settle');

        $this->processor->settle($payment);
    }

    public function test_it_lets_paypal_refusing_the_capture_through_and_discards_what_it_changed_on_the_payment(): void
    {
        $payment = $this->payment();
        $this->orderDetailsApi->method('get')->willReturn($this->order(['status' => 'APPROVED']));
        $this->completeOrderApi
            ->method('complete')
            ->willThrowException(new PayPalApiRequestException('POST', 'v2/checkout/orders/PAYPAL_ORDER_ID/capture', 422, 'UNPROCESSABLE_ENTITY', issues: ['ORDER_NOT_APPROVED']))
        ;
        $this->paymentManager->method('isOpen')->willReturn(true);
        $this->paymentManager->method('contains')->willReturn(true);

        $this->decoratedProcessor->expects(self::never())->method('settle');
        $this->connection->expects(self::once())->method('rollBack');
        $this->paymentManager->expects(self::exactly(2))->method('refresh');

        $this->expectException(PayPalApiRequestException::class);

        $this->processor->settle($payment);
    }

    public function test_it_cancels_the_payment_once_paypal_no_longer_returns_the_order_it_awaits_since_before_the_upgrade(): void
    {
        $payment = $this->payment(updatedAt: '2026-11-09 12:00:00');
        $this->connection->method('isTransactionActive')->willReturn(true);
        $this->orderDetailsApi->method('get')->willThrowException(new PayPalApiNotFoundException('GET', 'v2/checkout/orders/PAYPAL_ORDER_ID', 404, 'RESOURCE_NOT_FOUND'));
        $this->stateMachine->method('can')->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL)->willReturn(true);

        $this->stateMachine->expects(self::once())->method('apply')->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);
        $this->paymentManager->expects(self::once())->method('flush');
        $this->decoratedProcessor->expects(self::never())->method('settle');

        $this->processor->settle($payment);
    }

    public function test_it_lets_paypal_not_returning_an_order_it_may_still_hold_through(): void
    {
        $payment = $this->payment(updatedAt: '2026-11-10 12:00:01');
        $this->orderDetailsApi->method('get')->willThrowException(new PayPalApiNotFoundException('GET', 'v2/checkout/orders/PAYPAL_ORDER_ID', 404, 'RESOURCE_NOT_FOUND'));

        $this->stateMachine->expects(self::never())->method('apply');
        $this->connection->expects(self::once())->method('rollBack');

        $this->expectException(PayPalApiNotFoundException::class);

        $this->processor->settle($payment);
    }

    public function test_it_cancels_the_payment_instead_of_capturing_an_order_whose_card_authentication_did_not_finish(): void
    {
        $payment = $this->payment(details: ['payment_source' => 'card']);
        $order = $this->order(['status' => 'APPROVED']);
        $this->orderDetailsApi->method('get')->willReturn($order);
        $this->threeDSecureVerifier->method('verify')->with($order)->willThrowException(new ThreeDSecureAuthenticationFailedException(true));
        $this->stateMachine->method('can')->willReturn(true);

        $this->completeOrderApi->expects(self::never())->method('complete');
        $this->approvedPayPalOrderUpdater->expects(self::never())->method('update');
        $this->stateMachine->expects(self::once())->method('apply')->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);
        $payment->expects(self::once())->method('setDetails')->with(self::callback(
            static fn (array $details): bool => 'retry' === $details['three_d_secure_failure'],
        ));
        $this->decoratedProcessor->expects(self::never())->method('settle');

        $this->processor->settle($payment);
    }

    public function test_it_fails_the_payment_instead_of_capturing_an_order_whose_card_was_refused(): void
    {
        $payment = $this->payment(details: ['payment_source' => 'card']);
        $this->orderDetailsApi->method('get')->willReturn($this->order(['status' => 'APPROVED']));
        $this->threeDSecureVerifier->method('verify')->willThrowException(new ThreeDSecureAuthenticationFailedException(false));
        $this->stateMachine->method('can')->willReturn(true);

        $this->stateMachine->expects(self::once())->method('apply')->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL);
        $this->completeOrderApi->expects(self::never())->method('complete');

        $this->processor->settle($payment);
    }

    public function test_it_captures_a_card_order_paypal_still_reports_as_created(): void
    {
        $payment = $this->payment(details: ['payment_source' => 'card']);
        $this->orderDetailsApi->method('get')->willReturn($this->order(['status' => 'CREATED']));

        $this->approvedPayPalOrderUpdater->expects(self::once())->method('update')->with($payment);
        $this->completeOrderApi->expects(self::once())->method('complete')->with($this->paymentMethod, 'PAYPAL_ORDER_ID', self::REQUEST_ID);

        $this->processor->settle($payment);
    }

    public function test_it_discards_what_it_changed_on_the_order_of_the_payment_inside_the_transaction_of_its_caller(): void
    {
        $order = $this->createStub(OrderInterface::class);
        $payment = $this->payment(order: $order);
        $this->connection->method('isTransactionActive')->willReturn(true);
        $this->orderDetailsApi->method('get')->willReturn($this->order(['status' => 'APPROVED']));
        $this->completeOrderApi->method('complete')->willThrowException(new PayPalApiRequestException('POST', 'v2/checkout/orders/PAYPAL_ORDER_ID/capture', 422, 'UNPROCESSABLE_ENTITY', issues: ['ORDER_NOT_APPROVED']));
        $this->paymentManager->method('isOpen')->willReturn(true);
        $this->paymentManager->method('contains')->willReturn(true);

        $this->connection->expects(self::never())->method('rollBack');
        $refreshed = [];
        $this->paymentManager->method('refresh')->willReturnCallback(static function (object $entity) use (&$refreshed): void {
            $refreshed[] = $entity;
        });

        try {
            $this->processor->settle($payment);
            self::fail('The refusal of PayPal was not let through.');
        } catch (PayPalApiRequestException) {
        }

        self::assertSame([$payment, $order], $refreshed);
    }

    public function test_it_lets_the_original_error_through_when_the_entity_manager_is_closed(): void
    {
        $payment = $this->payment();
        $this->orderDetailsApi->method('get')->willReturn($this->order(['status' => 'APPROVED']));
        $this->completeOrderApi->method('complete')->willThrowException(new PayPalApiRequestException('POST', 'v2/checkout/orders/PAYPAL_ORDER_ID/capture', 422, 'UNPROCESSABLE_ENTITY', issues: ['ORDER_NOT_APPROVED']));
        $this->paymentManager->method('isOpen')->willReturn(false);

        $this->paymentManager->expects(self::once())->method('refresh');

        $this->expectException(PayPalApiRequestException::class);

        $this->processor->settle($payment);
    }

    public function test_it_captures_a_card_order_of_a_payment_that_stored_no_payment_source_by_the_payment_source_of_the_order(): void
    {
        $payment = $this->payment();
        $this->orderDetailsApi->method('get')->willReturn($this->order(['status' => 'CREATED', 'payment_source' => ['card' => ['last_digits' => '1111']]]));

        $this->completeOrderApi->expects(self::once())->method('complete');

        $this->processor->settle($payment);
    }

    public function test_it_leaves_an_approved_order_of_a_redirect_payment_source_to_paypal(): void
    {
        $payment = $this->payment(details: ['payment_source' => 'trustly']);
        $order = $this->order(['status' => 'APPROVED']);
        $this->orderDetailsApi->method('get')->willReturn($order);

        $this->completeOrderApi->expects(self::never())->method('complete');
        $this->approvedPayPalOrderUpdater->expects(self::never())->method('update');
        $this->decoratedProcessor->expects(self::once())->method('settle')->with($payment, $order, 'PAYPAL_ORDER_ID');

        $this->processor->settle($payment);
    }

    public function test_it_lets_another_not_found_error_of_paypal_through_even_after_the_lifetime_of_its_order(): void
    {
        $payment = $this->payment(updatedAt: '2026-11-01 12:00:00');
        $this->orderDetailsApi->method('get')->willThrowException(new PayPalApiNotFoundException('GET', 'v2/checkout/orders/PAYPAL_ORDER_ID', 404, 'NOT_FOUND'));

        $this->stateMachine->expects(self::never())->method('apply');

        $this->expectException(PayPalApiNotFoundException::class);

        $this->processor->settle($payment);
    }

    public function test_it_settles_the_payment_locked_in_a_transaction_of_its_own(): void
    {
        $payment = $this->payment();
        $this->orderDetailsApi->method('get')->willReturn($this->order(['status' => 'CREATED']));
        $this->connection->method('isTransactionActive')->willReturn(false);

        $this->connection->expects(self::once())->method('beginTransaction');
        $this->paymentManager->expects(self::once())->method('refresh')->with($payment, LockMode::PESSIMISTIC_WRITE);
        $this->connection->expects(self::once())->method('commit');

        $this->processor->settle($payment);
    }

    public function test_it_cancels_the_payment_once_paypal_voided_the_order_it_awaits_since_before_the_upgrade(): void
    {
        $payment = $this->payment();
        $this->orderDetailsApi->method('get')->willReturn($this->order(['status' => 'VOIDED']));
        $this->stateMachine->method('can')->willReturn(true);

        $this->stateMachine->expects(self::once())->method('apply')->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);
        $this->decoratedProcessor->expects(self::never())->method('settle');

        $this->processor->settle($payment);
    }

    public function test_it_leaves_an_order_the_buyer_has_not_approved_yet_to_the_settlement(): void
    {
        $payment = $this->payment();
        $order = $this->order(['status' => 'CREATED']);
        $this->orderDetailsApi->expects(self::once())->method('get')->willReturn($order);

        $this->completeOrderApi->expects(self::never())->method('complete');
        $this->stateMachine->expects(self::never())->method('apply');
        $this->decoratedProcessor->expects(self::once())->method('settle')->with($payment, $order, 'PAYPAL_ORDER_ID');

        $this->processor->settle($payment);
    }

    public function test_it_leaves_a_payment_not_awaiting_an_order_created_before_the_upgrade_to_the_settlement(): void
    {
        $payment = $this->payment(awaitingPreUpgradePayPalOrder: false);

        $this->orderDetailsApi->expects(self::never())->method('get');
        $this->decoratedProcessor->expects(self::once())->method('settle')->with($payment, null, null);

        $this->processor->settle($payment);
    }

    public function test_it_leaves_another_paypal_order_of_the_payment_to_the_settlement(): void
    {
        $payment = $this->payment();

        $this->orderDetailsApi->expects(self::never())->method('get');
        $this->decoratedProcessor->expects(self::once())->method('settle')->with($payment, null, 'SUPERSEDED_PAYPAL_ORDER_ID');

        $this->processor->settle($payment, null, 'SUPERSEDED_PAYPAL_ORDER_ID');
    }

    /** @param array<string, mixed> $details */
    private function payment(
        bool $awaitingPreUpgradePayPalOrder = true,
        string $updatedAt = '2026-11-12 11:00:00',
        array $details = [],
        ?OrderInterface $order = null,
    ): PaymentInterface&MockObject {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getUpdatedAt')->willReturn(new \DateTime($updatedAt));
        $payment->method('getState')->willReturn(PaymentInterface::STATE_PROCESSING);
        $payment->method('getMethod')->willReturn($this->paymentMethod);
        $payment->method('getDetails')->willReturn(['status' => 'CAPTURED', 'paypal_order_id' => 'PAYPAL_ORDER_ID'] + $details);
        $this->preUpgradePayPalOrderChecker->method('isAwaitingPreUpgradePayPalOrder')->with($payment)->willReturn($awaitingPreUpgradePayPalOrder);

        return $payment;
    }

    /** @param array<string, mixed> $order */
    private function order(array $order): PayPalOrderDetails
    {
        return (new PayPalOrderDetailsMapper())->map(['id' => 'PAYPAL_ORDER_ID'] + $order);
    }
}
