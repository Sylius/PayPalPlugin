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

namespace Tests\Sylius\PayPalPlugin\Unit\CommandHandler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Api\ChargeVaultedOrderApiInterface;
use Sylius\PayPalPlugin\Command\ChargeVaultedPaymentRequest;
use Sylius\PayPalPlugin\CommandHandler\ChargeVaultedPaymentRequestHandler;
use Sylius\PayPalPlugin\Exception\PayPalApiConnectionException;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Exception\PayPalApiServerException;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;

final class ChargeVaultedPaymentRequestHandlerTest extends TestCase
{
    private PaymentRequestInterface&MockObject $paymentRequest;

    private ChargeVaultedOrderApiInterface&MockObject $chargeVaultedOrderApi;

    private PaymentSettlementProcessorInterface&MockObject $paymentSettlementProcessor;

    private StateMachineInterface&MockObject $stateMachine;

    private Payment $payment;

    /** @var list<array{string, string}> */
    private array $appliedTransitions = [];

    private ChargeVaultedPaymentRequestHandler $handler;

    protected function setUp(): void
    {
        $this->payment = new Payment();
        $this->payment->setAmount(1500);
        $this->payment->setDetails(['vault_id' => 'VAULT_ID', 'vault_customer_id' => 'CUSTOMER_ID']);

        $this->paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $this->paymentRequest->method('getId')->willReturn('PAYMENT_REQUEST_HASH');
        $this->paymentRequest->method('getPayment')->willReturn($this->payment);

        $paymentRequestProvider = $this->createStub(PaymentRequestProviderInterface::class);
        $paymentRequestProvider->method('provide')->willReturn($this->paymentRequest);

        $this->chargeVaultedOrderApi = $this->createMock(ChargeVaultedOrderApiInterface::class);
        $this->paymentSettlementProcessor = $this->createMock(PaymentSettlementProcessorInterface::class);

        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->stateMachine->method('can')->willReturn(true);
        $this->stateMachine->method('apply')->willReturnCallback(function (object $subject, string $graph, string $transition): void {
            $this->appliedTransitions[] = [$graph, $transition];
        });

        $this->handler = new ChargeVaultedPaymentRequestHandler(
            $paymentRequestProvider,
            $this->chargeVaultedOrderApi,
            $this->paymentSettlementProcessor,
            $this->stateMachine,
        );
    }

    public function test_it_does_nothing_with_a_request_that_is_not_processing(): void
    {
        $this->paymentRequest->method('getState')->willReturn(PaymentRequestInterface::STATE_COMPLETED);

        $this->chargeVaultedOrderApi->expects(self::never())->method('charge');

        ($this->handler)(new ChargeVaultedPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([], $this->appliedTransitions);
    }

    public function test_it_charges_the_vaulted_wallet_and_completes_the_request_once_paypal_captured_it(): void
    {
        $this->paymentRequest->method('getState')->willReturn(PaymentRequestInterface::STATE_PROCESSING);
        $payPalOrder = $this->payPalOrder('COMPLETED');

        $this->chargeVaultedOrderApi
            ->expects(self::once())
            ->method('charge')
            ->with($this->payment, 'VAULT_ID', 'PAYMENT_REQUEST_HASH', 'PAYMENT_REQUEST_HASH')
            ->willReturn($payPalOrder)
        ;
        $this->paymentSettlementProcessor->expects(self::once())->method('settle')->with($this->payment, $payPalOrder);

        ($this->handler)(new ChargeVaultedPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([
            'vault_id' => 'VAULT_ID',
            'vault_customer_id' => 'CUSTOMER_ID',
            'status' => 'PROCESSING',
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            'reference_id' => 'PAYMENT_REQUEST_HASH',
            'payment_amount' => 1500,
            'payment_source' => 'paypal',
        ], $this->payment->getDetails());
        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE]], $this->appliedTransitions);
    }

    public function test_it_keeps_the_payment_processing_while_paypal_holds_the_capture_pending(): void
    {
        $this->paymentRequest->method('getState')->willReturn(PaymentRequestInterface::STATE_PROCESSING);
        $this->chargeVaultedOrderApi->method('charge')->willReturn($this->payPalOrder('PENDING'));

        ($this->handler)(new ChargeVaultedPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([
            [PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS],
            [PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE],
        ], $this->appliedTransitions);
    }

    public function test_it_fails_the_request_when_paypal_declined_the_capture(): void
    {
        $this->paymentRequest->method('getState')->willReturn(PaymentRequestInterface::STATE_PROCESSING);
        $payPalOrder = $this->payPalOrder('DECLINED');
        $this->chargeVaultedOrderApi->method('charge')->willReturn($payPalOrder);

        $this->paymentSettlementProcessor->expects(self::once())->method('settle')->with($this->payment, $payPalOrder);
        $this->paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'PayPal reported the capture as DECLINED.']);

        ($this->handler)(new ChargeVaultedPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([[PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL]], $this->appliedTransitions);
    }

    public function test_it_fails_the_payment_with_the_issues_paypal_refused_the_charge_for(): void
    {
        $this->paymentRequest->method('getState')->willReturn(PaymentRequestInterface::STATE_PROCESSING);
        $this->chargeVaultedOrderApi->method('charge')->willThrowException(
            new PayPalApiRequestException('POST', 'v2/checkout/orders', 422, 'UNPROCESSABLE_ENTITY', issues: ['INSTRUMENT_DECLINED']),
        );

        $this->paymentSettlementProcessor->expects(self::never())->method('settle');
        $this->paymentRequest->expects(self::once())->method('setResponseData')->with([
            'reason' => 'PayPal refused to charge the vaulted wallet.',
            'issues' => ['INSTRUMENT_DECLINED'],
        ]);

        ($this->handler)(new ChargeVaultedPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([
            [PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL],
            [PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL],
        ], $this->appliedTransitions);
    }

    public function test_it_fails_the_payment_when_paypal_answered_without_a_capture(): void
    {
        $this->paymentRequest->method('getState')->willReturn(PaymentRequestInterface::STATE_PROCESSING);
        $this->chargeVaultedOrderApi->method('charge')->willReturn(['id' => 'PAYPAL_ORDER_ID', 'status' => 'PAYER_ACTION_REQUIRED']);

        $this->paymentSettlementProcessor->expects(self::never())->method('settle');
        $this->paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'PayPal did not capture the order.']);

        ($this->handler)(new ChargeVaultedPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([
            [PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL],
            [PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL],
        ], $this->appliedTransitions);
    }

    public function test_it_settles_from_the_order_details_when_paypal_completed_the_order_without_returning_the_capture(): void
    {
        $this->paymentRequest->method('getState')->willReturn(PaymentRequestInterface::STATE_PROCESSING);
        $this->chargeVaultedOrderApi->method('charge')->willReturn(['id' => 'PAYPAL_ORDER_ID', 'status' => 'COMPLETED']);

        $this->paymentSettlementProcessor->expects(self::once())->method('settle')->with($this->payment);
        $this->paymentRequest->expects(self::never())->method('setResponseData');

        ($this->handler)(new ChargeVaultedPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame('PAYPAL_ORDER_ID', $this->payment->getDetails()['paypal_order_id']);
        self::assertSame([
            [PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS],
            [PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE],
        ], $this->appliedTransitions);
    }

    public function test_it_fails_the_payment_that_carries_no_vaulted_wallet_without_calling_paypal(): void
    {
        $this->paymentRequest->method('getState')->willReturn(PaymentRequestInterface::STATE_PROCESSING);
        $this->payment->setDetails([]);

        $this->chargeVaultedOrderApi->expects(self::never())->method('charge');
        $this->paymentRequest->expects(self::once())->method('setResponseData')->with(['reason' => 'The payment carries no vaulted PayPal wallet.']);

        ($this->handler)(new ChargeVaultedPaymentRequest('PAYMENT_REQUEST_HASH'));

        self::assertSame([
            [PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL],
            [PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL],
        ], $this->appliedTransitions);
    }

    /** @return iterable<string, array{\Throwable}> */
    public static function unknownOutcomes(): iterable
    {
        yield 'paypal unreachable' => [new PayPalApiConnectionException('POST', 'v2/checkout/orders')];
        yield 'paypal server error' => [new PayPalApiServerException('POST', 'v2/checkout/orders', 503)];
    }

    #[DataProvider('unknownOutcomes')]
    public function test_it_leaves_the_payment_and_the_request_untouched_when_the_outcome_of_the_charge_is_unknown(\Throwable $exception): void
    {
        $this->paymentRequest->method('getState')->willReturn(PaymentRequestInterface::STATE_PROCESSING);
        $this->chargeVaultedOrderApi->method('charge')->willThrowException($exception);

        try {
            ($this->handler)(new ChargeVaultedPaymentRequest('PAYMENT_REQUEST_HASH'));
            self::fail('The handler should let the unknown outcome through.');
        } catch (\Throwable $thrown) {
            self::assertSame($exception, $thrown);
        }

        self::assertSame([], $this->appliedTransitions);
        self::assertSame(['vault_id' => 'VAULT_ID', 'vault_customer_id' => 'CUSTOMER_ID'], $this->payment->getDetails());
    }

    /** @return array<string, mixed> */
    private function payPalOrder(string $captureStatus): array
    {
        return [
            'id' => 'PAYPAL_ORDER_ID',
            'status' => 'COMPLETED',
            'purchase_units' => [['payments' => ['captures' => [['id' => 'CAPTURE_ID', 'status' => $captureStatus]]]]],
        ];
    }
}
