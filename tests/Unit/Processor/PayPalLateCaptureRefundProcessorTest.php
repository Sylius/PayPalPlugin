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
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Api\RefundPaymentApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalOrderRefundException;
use Sylius\PayPalPlugin\Generator\PayPalAuthAssertionGeneratorInterface;
use Sylius\PayPalPlugin\Processor\PaymentRefundProcessorInterface;
use Sylius\PayPalPlugin\Processor\PayPalLateCaptureRefundProcessor;
use Sylius\PayPalPlugin\Processor\PayPalPaymentSettlementProcessor;
use Sylius\PayPalPlugin\Provider\RefundReferenceNumberProviderInterface;

final class PayPalLateCaptureRefundProcessorTest extends TestCase
{
    private OrderDetailsApiInterface&Stub $orderDetailsApi;

    private RefundPaymentApiInterface&MockObject $refundPaymentApi;

    private ObjectManager&MockObject $paymentManager;

    private PayPalLateCaptureRefundProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $authorizeClientApi = $this->createStub(CacheAuthorizeClientApiInterface::class);
        $authorizeClientApi->method('authorize')->willReturn('TOKEN');
        $authAssertionGenerator = $this->createStub(PayPalAuthAssertionGeneratorInterface::class);
        $authAssertionGenerator->method('generate')->willReturn('AUTH_ASSERTION');
        $referenceNumberProvider = $this->createStub(RefundReferenceNumberProviderInterface::class);
        $referenceNumberProvider->method('provide')->willReturn('REFERENCE_NUMBER');

        $this->orderDetailsApi = $this->createStub(OrderDetailsApiInterface::class);
        $this->orderDetailsApi->method('get')->willReturn($this->orderDetails('COMPLETED'));
        $this->refundPaymentApi = $this->createMock(RefundPaymentApiInterface::class);
        $this->paymentManager = $this->createMock(ObjectManager::class);

        $this->processor = new PayPalLateCaptureRefundProcessor(
            $authorizeClientApi,
            $this->orderDetailsApi,
            $this->refundPaymentApi,
            $authAssertionGenerator,
            $referenceNumberProvider,
            $this->paymentManager,
        );
    }

    public function test_it_implements_payment_refund_processor_interface(): void
    {
        self::assertInstanceOf(PaymentRefundProcessorInterface::class, $this->processor);
    }

    public function test_it_refunds_the_late_capture_and_records_the_refund(): void
    {
        $payment = $this->payment(['id' => 'CAPTURE_ID', 'amount' => 1539, 'currency_code' => 'EUR']);

        $this->refundPaymentApi
            ->expects(self::once())
            ->method('refund')
            ->with('TOKEN', 'CAPTURE_ID', 'AUTH_ASSERTION', 'REFERENCE_NUMBER', '15.39', 'EUR')
            ->willReturn(['id' => 'REFUND_ID', 'status' => 'COMPLETED'])
        ;
        $payment->expects(self::once())->method('setDetails')->with([
            'paypal_order_id' => 'PAYPAL_ORDER_ID',
            PayPalPaymentSettlementProcessor::LATE_CAPTURE => [
                'id' => 'CAPTURE_ID',
                'amount' => 1539,
                'currency_code' => 'EUR',
                'refunded' => true,
                'refund_id' => 'REFUND_ID',
            ],
        ]);
        $this->paymentManager->expects(self::once())->method('flush');

        $this->processor->refund($payment);
    }

    public function test_it_refunds_the_amount_in_the_decimals_of_the_captured_currency(): void
    {
        $payment = $this->payment(['id' => 'CAPTURE_ID', 'amount' => 150000, 'currency_code' => 'JPY']);

        $this->refundPaymentApi
            ->expects(self::once())
            ->method('refund')
            ->with('TOKEN', 'CAPTURE_ID', 'AUTH_ASSERTION', 'REFERENCE_NUMBER', '1500', 'JPY')
            ->willReturn(['id' => 'REFUND_ID', 'status' => 'COMPLETED'])
        ;

        $this->processor->refund($payment);
    }

    public function test_it_records_a_capture_paypal_already_refunded_without_refunding_it_again(): void
    {
        $orderDetailsApi = $this->createStub(OrderDetailsApiInterface::class);
        $orderDetailsApi->method('get')->willReturn($this->orderDetails('REFUNDED'));
        $processor = $this->processorWith($orderDetailsApi);
        $payment = $this->payment(['id' => 'CAPTURE_ID', 'amount' => 1539, 'currency_code' => 'EUR']);

        $this->refundPaymentApi->expects(self::never())->method('refund');
        $payment->expects(self::once())->method('setDetails')->with(self::callback(
            static fn (array $details): bool => true === $details[PayPalPaymentSettlementProcessor::LATE_CAPTURE]['refunded'],
        ));
        $this->paymentManager->expects(self::once())->method('flush');

        $processor->refund($payment);
    }

    public function test_it_fails_when_paypal_refuses_the_refund(): void
    {
        $payment = $this->payment(['id' => 'CAPTURE_ID', 'amount' => 1539, 'currency_code' => 'EUR']);
        $this->refundPaymentApi->method('refund')->willReturn(['name' => 'UNPROCESSABLE_ENTITY', 'debug_id' => 'DEBUG_ID']);

        $payment->expects(self::never())->method('setDetails');
        $this->paymentManager->expects(self::never())->method('flush');

        $this->expectException(PayPalOrderRefundException::class);

        $this->processor->refund($payment);
    }

    public function test_it_fails_when_paypal_reports_the_refund_failed(): void
    {
        $payment = $this->payment(['id' => 'CAPTURE_ID', 'amount' => 1539, 'currency_code' => 'EUR']);
        $this->refundPaymentApi->method('refund')->willReturn(['id' => 'REFUND_ID', 'status' => 'FAILED']);

        $this->expectException(PayPalOrderRefundException::class);

        $this->processor->refund($payment);
    }

    public function test_it_refunds_nothing_for_a_payment_without_a_late_capture(): void
    {
        $this->refundPaymentApi->expects(self::never())->method('refund');

        $this->expectException(PayPalOrderRefundException::class);

        $this->processor->refund($this->payment(null));
    }

    public function test_it_refunds_a_late_capture_only_once(): void
    {
        $this->refundPaymentApi->expects(self::never())->method('refund');

        $this->expectException(PayPalOrderRefundException::class);

        $this->processor->refund($this->payment(['id' => 'CAPTURE_ID', 'amount' => 1539, 'currency_code' => 'EUR', 'refunded' => true]));
    }

    public function test_it_refunds_nothing_for_a_payment_of_another_gateway(): void
    {
        $this->refundPaymentApi->expects(self::never())->method('refund');

        $this->expectException(PayPalOrderRefundException::class);

        $this->processor->refund($this->payment(['id' => 'CAPTURE_ID', 'amount' => 1539, 'currency_code' => 'EUR'], 'offline'));
    }

    /** @param array<string, mixed>|null $lateCapture */
    private function payment(?array $lateCapture, string $factoryName = 'sylius_paypal'): PaymentInterface&MockObject
    {
        $gatewayConfig = $this->createStub(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);
        $paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $details = ['paypal_order_id' => 'PAYPAL_ORDER_ID'];
        if (null !== $lateCapture) {
            $details[PayPalPaymentSettlementProcessor::LATE_CAPTURE] = $lateCapture;
        }

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getDetails')->willReturn($details);

        return $payment;
    }

    private function processorWith(OrderDetailsApiInterface $orderDetailsApi): PayPalLateCaptureRefundProcessor
    {
        $authorizeClientApi = $this->createStub(CacheAuthorizeClientApiInterface::class);
        $authorizeClientApi->method('authorize')->willReturn('TOKEN');

        return new PayPalLateCaptureRefundProcessor(
            $authorizeClientApi,
            $orderDetailsApi,
            $this->refundPaymentApi,
            $this->createStub(PayPalAuthAssertionGeneratorInterface::class),
            $this->createStub(RefundReferenceNumberProviderInterface::class),
            $this->paymentManager,
        );
    }

    /** @return array<string, mixed> */
    private function orderDetails(string $captureStatus): array
    {
        return [
            'id' => 'PAYPAL_ORDER_ID',
            'status' => 'COMPLETED',
            'purchase_units' => [[
                'payments' => [
                    'captures' => [[
                        'id' => 'CAPTURE_ID',
                        'status' => $captureStatus,
                        'amount' => ['currency_code' => 'EUR', 'value' => '15.39'],
                    ]],
                ],
            ]],
        ];
    }
}
