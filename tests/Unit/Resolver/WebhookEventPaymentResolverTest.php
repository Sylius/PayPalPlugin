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

namespace Tests\Sylius\PayPalPlugin\Unit\Resolver;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Model\PayPalWebhookEventNotification;
use Sylius\PayPalPlugin\Repository\Query\SettleablePaypalPaymentQueryInterface;
use Sylius\PayPalPlugin\Repository\Query\VaultedPaypalPaymentQueryInterface;
use Sylius\PayPalPlugin\Resolver\WebhookEventPaymentResolver;

final class WebhookEventPaymentResolverTest extends TestCase
{
    private const PAYMENT_REQUEST_HASH = '0192f0b8-4d0b-7d4a-9b5e-3c2a1f6e8d10';

    /** @var PaymentRequestRepositoryInterface<PaymentRequestInterface>&MockObject */
    private PaymentRequestRepositoryInterface&MockObject $paymentRequestRepository;

    private SettleablePaypalPaymentQueryInterface&VaultedPaypalPaymentQueryInterface&MockObject $paypalPaymentQuery;

    private WebhookEventPaymentResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentRequestRepository = $this->createMock(PaymentRequestRepositoryInterface::class);
        $this->paypalPaymentQuery = $this->createMockForIntersectionOfInterfaces([
            SettleablePaypalPaymentQueryInterface::class,
            VaultedPaypalPaymentQueryInterface::class,
        ]);

        $this->resolver = new WebhookEventPaymentResolver($this->paymentRequestRepository, $this->paypalPaymentQuery);
    }

    public function test_it_resolves_the_payment_of_the_payment_request_paypal_was_given_as_custom_id(): void
    {
        $payment = $this->createStub(PaymentInterface::class);
        $this->paymentRequestRepository->method('find')->with(self::PAYMENT_REQUEST_HASH)->willReturn($this->paymentRequestOf($payment));

        $this->paypalPaymentQuery->expects(self::never())->method('getForSettlementByOrderId');

        self::assertSame($payment, $this->resolver->resolve($this->notification('capture', [
            'id' => 'CAPTURE_ID',
            'custom_id' => self::PAYMENT_REQUEST_HASH,
            'supplementary_data' => ['related_ids' => ['order_id' => 'PAYPAL_ORDER_ID']],
        ])));
    }

    public function test_it_resolves_the_payment_request_named_in_a_purchase_unit_of_a_checkout_order(): void
    {
        $payment = $this->createStub(PaymentInterface::class);
        $this->paymentRequestRepository->method('find')->with(self::PAYMENT_REQUEST_HASH)->willReturn($this->paymentRequestOf($payment));

        self::assertSame($payment, $this->resolver->resolve($this->notification('checkout-order', [
            'id' => 'PAYPAL_ORDER_ID',
            'purchase_units' => [['custom_id' => self::PAYMENT_REQUEST_HASH]],
        ])));
    }

    public function test_it_does_not_look_up_a_custom_id_that_is_not_a_payment_request_hash(): void
    {
        $payment = $this->createStub(PaymentInterface::class);

        $this->paymentRequestRepository->expects(self::never())->method('find');
        $this->paypalPaymentQuery->method('getForSettlementByOrderId')->with('PAYPAL_ORDER_ID')->willReturn($payment);

        self::assertSame($payment, $this->resolver->resolve($this->notification('capture', [
            'id' => 'CAPTURE_ID',
            'custom_id' => '000000123-REFERENCE',
            'supplementary_data' => ['related_ids' => ['order_id' => 'PAYPAL_ORDER_ID']],
        ])));
    }

    public function test_it_falls_back_to_the_paypal_order_of_a_capture(): void
    {
        $payment = $this->createStub(PaymentInterface::class);
        $this->paymentRequestRepository->method('find')->willReturn(null);
        $this->paypalPaymentQuery->method('getForSettlementByOrderId')->with('PAYPAL_ORDER_ID')->willReturn($payment);

        self::assertSame($payment, $this->resolver->resolve($this->notification('capture', [
            'id' => 'CAPTURE_ID',
            'custom_id' => self::PAYMENT_REQUEST_HASH,
            'supplementary_data' => ['related_ids' => ['order_id' => 'PAYPAL_ORDER_ID']],
        ])));
    }

    public function test_it_falls_back_to_the_id_of_a_checkout_order(): void
    {
        $payment = $this->createStub(PaymentInterface::class);
        $this->paypalPaymentQuery->method('getForSettlementByOrderId')->with('PAYPAL_ORDER_ID')->willReturn($payment);

        self::assertSame($payment, $this->resolver->resolve($this->notification('checkout-order', ['id' => 'PAYPAL_ORDER_ID'])));
    }

    public function test_it_falls_back_to_the_capture_id_when_no_payment_carries_the_paypal_order(): void
    {
        $payment = $this->createStub(PaymentInterface::class);
        $this->paypalPaymentQuery->method('getForSettlementByOrderId')->willThrowException(new PaymentNotFoundException());
        $this->paypalPaymentQuery->method('getForSettlementByCaptureId')->with('CAPTURE_ID')->willReturn($payment);

        self::assertSame($payment, $this->resolver->resolve($this->notification('capture', [
            'id' => 'CAPTURE_ID',
            'supplementary_data' => ['related_ids' => ['order_id' => 'PAYPAL_ORDER_ID']],
        ])));
    }

    public function test_it_resolves_the_payment_of_a_refund_by_the_capture_it_refunds(): void
    {
        $payment = $this->createStub(PaymentInterface::class);
        $this->paypalPaymentQuery->expects(self::never())->method('getForSettlementByOrderId');
        $this->paypalPaymentQuery->method('getForSettlementByCaptureId')->with('CAPTURE_ID')->willReturn($payment);

        self::assertSame($payment, $this->resolver->resolve($this->notification('refund', [
            'id' => 'REFUND_ID',
            'links' => [
                ['rel' => 'self', 'href' => 'https://api.paypal.com/v2/payments/refunds/REFUND_ID'],
                ['rel' => 'up', 'href' => 'https://api.paypal.com/v2/payments/captures/CAPTURE_ID'],
            ],
        ])));
    }

    public function test_it_resolves_nothing_when_no_id_of_the_event_leads_to_a_payment(): void
    {
        $this->paypalPaymentQuery->method('getForSettlementByOrderId')->willThrowException(new PaymentNotFoundException());
        $this->paypalPaymentQuery->method('getForSettlementByCaptureId')->willThrowException(new PaymentNotFoundException());

        self::assertNull($this->resolver->resolve($this->notification('capture', [
            'id' => 'CAPTURE_ID',
            'supplementary_data' => ['related_ids' => ['order_id' => 'PAYPAL_ORDER_ID']],
        ])));
    }

    public function test_it_resolves_nothing_for_a_refund_that_does_not_point_at_a_capture(): void
    {
        $this->paypalPaymentQuery->expects(self::never())->method('getForSettlementByCaptureId');

        self::assertNull($this->resolver->resolve($this->notification('refund', [
            'id' => 'REFUND_ID',
            'links' => [['rel' => 'up', 'href' => 'https://api.paypal.com/v2/checkout/orders/PAYPAL_ORDER_ID']],
        ])));
    }

    public function test_it_resolves_the_payment_of_the_paypal_order_a_token_was_saved_during(): void
    {
        $payment = $this->createStub(PaymentInterface::class);
        $this->paypalPaymentQuery->method('getForSettlementByOrderId')->with('PAYPAL_ORDER_ID')->willReturn($payment);

        self::assertSame($payment, $this->resolver->resolve($this->notification('payment_token', [
            'id' => 'VAULT_ID',
            'metadata' => ['order_id' => 'PAYPAL_ORDER_ID'],
        ])));
    }

    public function test_it_resolves_a_payment_that_carries_the_token_when_the_event_names_no_paypal_order(): void
    {
        $payment = $this->createStub(PaymentInterface::class);
        $this->paypalPaymentQuery->method('findByVaultId')->with('VAULT_ID')->willReturn([$payment, $this->createStub(PaymentInterface::class)]);

        self::assertSame($payment, $this->resolver->resolve($this->notification('payment_token', ['id' => 'VAULT_ID'])));
    }

    public function test_it_resolves_nothing_for_a_token_no_payment_carries(): void
    {
        $this->paypalPaymentQuery->method('findByVaultId')->willReturn([]);

        self::assertNull($this->resolver->resolve($this->notification('payment_token', ['id' => 'VAULT_ID'])));
    }

    public function test_it_does_not_look_up_tokens_for_other_resources(): void
    {
        $this->paypalPaymentQuery->method('getForSettlementByCaptureId')->willThrowException(new PaymentNotFoundException());
        $this->paypalPaymentQuery->expects(self::never())->method('findByVaultId');

        self::assertNull($this->resolver->resolve($this->notification('capture', ['id' => 'CAPTURE_ID'])));
    }

    /** @param array<string, mixed> $resource */
    private function notification(string $resourceType, array $resource): PayPalWebhookEventNotification
    {
        return new PayPalWebhookEventNotification('WH-1', 'EVENT.TYPE', $resourceType, (string) $resource['id'], $resource);
    }

    private function paymentRequestOf(PaymentInterface $payment): PaymentRequestInterface
    {
        $paymentRequest = $this->createStub(PaymentRequestInterface::class);
        $paymentRequest->method('getPayment')->willReturn($payment);

        return $paymentRequest;
    }
}
