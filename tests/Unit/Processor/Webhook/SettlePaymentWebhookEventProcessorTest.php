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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Sylius\PayPalPlugin\Processor\Webhook\SettlePaymentWebhookEventProcessor;

final class SettlePaymentWebhookEventProcessorTest extends TestCase
{
    private PaymentSettlementProcessorInterface&MockObject $paymentSettlementProcessor;

    private SettlePaymentWebhookEventProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentSettlementProcessor = $this->createMock(PaymentSettlementProcessorInterface::class);

        $this->processor = new SettlePaymentWebhookEventProcessor($this->paymentSettlementProcessor);
    }

    public function test_it_settles_the_payment_against_the_paypal_order_of_the_capture(): void
    {
        $payment = $this->createStub(PaymentInterface::class);

        $this->paymentSettlementProcessor->expects(self::once())->method('settle')->with($payment, null, 'PAYPAL_ORDER_ID');

        $this->processor->process('PAYMENT.CAPTURE.COMPLETED', [
            'id' => 'CAPTURE_ID',
            'supplementary_data' => ['related_ids' => ['order_id' => 'PAYPAL_ORDER_ID']],
        ], $payment);
    }

    public function test_it_settles_the_payment_against_its_own_paypal_order_when_the_capture_names_none(): void
    {
        $payment = $this->createStub(PaymentInterface::class);

        $this->paymentSettlementProcessor->expects(self::once())->method('settle')->with($payment, null, null);

        $this->processor->process('PAYMENT.CAPTURE.PENDING', ['id' => 'CAPTURE_ID'], $payment);
    }

    public function test_it_settles_the_payment_with_a_completed_checkout_order_it_already_has(): void
    {
        $payment = $this->createStub(PaymentInterface::class);
        $order = ['id' => 'PAYPAL_ORDER_ID', 'status' => 'COMPLETED'];

        $this->paymentSettlementProcessor->expects(self::once())->method('settle')->with($payment, PayPalOrderDetails::fromArray($order), 'PAYPAL_ORDER_ID');

        $this->processor->process('CHECKOUT.ORDER.COMPLETED', $order, $payment);
    }
}
