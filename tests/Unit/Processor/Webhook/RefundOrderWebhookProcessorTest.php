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

namespace Tests\Sylius\PayPalPlugin\Unit\Processor\Webhook;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Exception\PayPalWrongDataException;
use Sylius\PayPalPlugin\Processor\Webhook\RefundOrderWebhookProcessor;
use Sylius\PayPalPlugin\Processor\Webhook\WebhookProcessorInterface;
use Sylius\PayPalPlugin\Provider\PayPalRefundDataProviderInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;

final class RefundOrderWebhookProcessorTest extends TestCase
{
    private PayPalRefundDataProviderInterface&MockObject $payPalRefundDataProvider;

    private PaypalPaymentQueryInterface&MockObject $paypalPaymentQuery;

    private StateMachineInterface&MockObject $stateMachine;

    private ObjectManager&MockObject $paymentManager;

    private RefundOrderWebhookProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payPalRefundDataProvider = $this->createMock(PayPalRefundDataProviderInterface::class);
        $this->paypalPaymentQuery = $this->createMock(PaypalPaymentQueryInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->paymentManager = $this->createMock(ObjectManager::class);

        $this->processor = new RefundOrderWebhookProcessor(
            $this->payPalRefundDataProvider,
            $this->paypalPaymentQuery,
            $this->stateMachine,
            $this->paymentManager,
        );
    }

    public function test_it_implements_webhook_processor_interface(): void
    {
        self::assertInstanceOf(WebhookProcessorInterface::class, $this->processor);
    }

    public function test_it_handles_refunds_and_nothing_else(): void
    {
        self::assertTrue($this->processor->supports('PAYMENT.CAPTURE.REFUNDED'));
        self::assertFalse($this->processor->supports('PAYMENT.CAPTURE.COMPLETED'));
    }

    public function test_it_refunds_the_payment_behind_the_up_link(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->payPalRefundDataProvider
            ->expects(self::once())
            ->method('provide')
            ->with('https://api-m.paypal.com/v2/checkout/orders/PAYPAL_ORDER_ID')
            ->willReturn(['id' => 'PAYPAL_ORDER_ID'])
        ;
        $this->paypalPaymentQuery->method('getForRefundingByOrderId')->with('PAYPAL_ORDER_ID')->willReturn($payment);
        $this->stateMachine->method('can')->willReturn(true);

        $this->stateMachine
            ->expects(self::once())
            ->method('apply')
            ->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND)
        ;
        $this->paymentManager->expects(self::once())->method('flush');

        $this->processor->process($this->payload());
    }

    public function test_it_leaves_a_payment_that_cannot_be_refunded_alone(): void
    {
        $this->paypalPaymentQuery->method('getForRefundingByOrderId')->willReturn($this->createMock(PaymentInterface::class));
        $this->payPalRefundDataProvider->method('provide')->willReturn(['id' => 'PAYPAL_ORDER_ID']);
        $this->stateMachine->method('can')->willReturn(false);

        $this->stateMachine->expects(self::never())->method('apply');

        $this->processor->process($this->payload());
    }

    public function test_it_stays_quiet_about_a_refund_of_an_order_it_does_not_know(): void
    {
        $this->payPalRefundDataProvider->method('provide')->willReturn(['id' => 'PAYPAL_ORDER_ID']);
        $this->paypalPaymentQuery
            ->method('getForRefundingByOrderId')
            ->willThrowException(new PaymentNotFoundException())
        ;

        $this->stateMachine->expects(self::never())->method('apply');

        $this->processor->process($this->payload());
    }

    public function test_it_refuses_a_refund_event_without_an_up_link(): void
    {
        $this->expectException(PayPalWrongDataException::class);

        $this->processor->process(['resource' => ['links' => [['rel' => 'self', 'href' => 'https://example.com']]]]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'links' => [
                    ['rel' => 'self', 'href' => 'https://api-m.paypal.com/v2/payments/refunds/REFUND_ID'],
                    ['rel' => 'up', 'href' => 'https://api-m.paypal.com/v2/checkout/orders/PAYPAL_ORDER_ID'],
                ],
            ],
        ];
    }
}
