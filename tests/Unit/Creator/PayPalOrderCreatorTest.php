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

namespace Tests\Sylius\PayPalPlugin\Unit\Creator;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CreateOrderApiInterface;
use Sylius\PayPalPlugin\Creator\PayPalOrderCreator;
use Sylius\PayPalPlugin\Creator\PayPalOrderCreatorInterface;
use Sylius\PayPalPlugin\Model\PayPalPaymentStatus;
use Sylius\PayPalPlugin\Provider\NonceProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalOrderCreatedStatusesProvider;
use Sylius\PayPalPlugin\Provider\PayPalOrderCreatedStatusesProviderInterface;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;

final class PayPalOrderCreatorTest extends TestCase
{
    private CacheAuthorizeClientApiInterface&MockObject $authorizeClientApi;

    private CreateOrderApiInterface&MockObject $createOrderApi;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private PaymentInterface&MockObject $payment;

    private PayPalOrderCreator $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorizeClientApi = $this->createMock(CacheAuthorizeClientApiInterface::class);
        $this->createOrderApi = $this->createMock(CreateOrderApiInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->payment = $this->createMock(PaymentInterface::class);

        $this->payment->method('getMethod')->willReturn($this->paymentMethod);
        $this->payment->method('getAmount')->willReturn(1000);
        $this->authorizeClientApi->method('authorize')->with($this->paymentMethod)->willReturn('ACCESS_TOKEN');

        $uuidProvider = $this->createMock(UuidProviderInterface::class);
        $uuidProvider->method('provide')->willReturn('UUID');

        $nonceProvider = $this->createMock(NonceProviderInterface::class);
        $nonceProvider->method('provide')->willReturnOnConsecutiveCalls('RETURN_NONCE', 'CANCEL_NONCE');

        $this->creator = new PayPalOrderCreator(
            $this->authorizeClientApi,
            $this->createOrderApi,
            $uuidProvider,
            new PayPalOrderCreatedStatusesProvider(),
            $nonceProvider,
        );
    }

    public function test_it_implements_paypal_order_creator_interface(): void
    {
        self::assertInstanceOf(PayPalOrderCreatorInterface::class, $this->creator);
    }

    public function test_it_creates_the_order_for_the_payment_source_and_records_it_on_the_payment(): void
    {
        $this->createOrderApi->expects(self::once())->method('create')->with('ACCESS_TOKEN', $this->payment, 'UUID', 'google_pay', null, null, null, null)->willReturn(['status' => 'CREATED', 'id' => '123123']);

        $this->payment->expects(self::once())->method('setDetails')->with([
            'status' => PayPalPaymentStatus::Captured->value,
            'paypal_order_id' => '123123',
            'reference_id' => 'UUID',
            'payment_amount' => 1000,
            'payment_source' => 'google_pay',
        ]);

        $details = $this->creator->create($this->payment, 'google_pay');

        self::assertSame('123123', $details?->payPalOrderId());
    }

    public function test_it_records_an_order_paypal_needs_the_payer_to_act_on(): void
    {
        $this->createOrderApi->method('create')->willReturn(['status' => 'PAYER_ACTION_REQUIRED', 'id' => '123123']);

        $this->payment->expects(self::once())->method('setDetails');

        self::assertNotNull($this->creator->create($this->payment, 'paypal'));
    }

    public function test_it_names_the_order_and_its_request_as_it_is_told(): void
    {
        $this->createOrderApi->expects(self::once())->method('create')->with('ACCESS_TOKEN', $this->payment, 'UUID', 'paypal', null, null, 'CUSTOM_ID', 'REQUEST_ID')->willReturn(['status' => 'CREATED', 'id' => '123123']);

        $this->creator->create($this->payment, 'paypal', 'CUSTOM_ID', 'REQUEST_ID');
    }

    public function test_it_leaves_the_payment_alone_when_paypal_does_not_confirm_the_order(): void
    {
        $this->createOrderApi->method('create')->willReturn(['name' => 'UNPROCESSABLE_ENTITY']);

        $this->payment->expects(self::never())->method('setDetails');

        self::assertNull($this->creator->create($this->payment, 'paypal'));
    }

    public function test_it_treats_as_created_only_the_statuses_its_provider_names(): void
    {
        $orderCreatedStatusesProvider = $this->createMock(PayPalOrderCreatedStatusesProviderInterface::class);
        $orderCreatedStatusesProvider->method('provide')->willReturn(['SOME_OTHER_STATUS']);

        $creator = new PayPalOrderCreator(
            $this->authorizeClientApi,
            $this->createOrderApi,
            $this->createMock(UuidProviderInterface::class),
            $orderCreatedStatusesProvider,
            $this->createMock(NonceProviderInterface::class),
        );
        $this->createOrderApi->method('create')->willReturn(['status' => 'CREATED', 'id' => '123123']);

        $this->payment->expects(self::never())->method('setDetails');

        self::assertNull($creator->create($this->payment, 'paypal'));
    }

    public function test_it_keeps_the_link_paypal_sends_the_payer_to(): void
    {
        $this->createOrderApi->method('create')->willReturn([
            'status' => 'PAYER_ACTION_REQUIRED',
            'id' => '123123',
            'links' => [
                ['href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/123123', 'rel' => 'self', 'method' => 'GET'],
                ['href' => 'https://www.sandbox.paypal.com/payment/trustly?token=123123', 'rel' => 'payer-action', 'method' => 'GET'],
            ],
        ]);

        $this->payment->expects(self::once())->method('setDetails')->with([
            'status' => PayPalPaymentStatus::Captured->value,
            'paypal_order_id' => '123123',
            'reference_id' => 'UUID',
            'payment_amount' => 1000,
            'payment_source' => 'trustly',
            'payer_action_url' => 'https://www.sandbox.paypal.com/payment/trustly?token=123123',
            'payer_action_return_nonce' => 'RETURN_NONCE',
            'payer_action_cancel_nonce' => 'CANCEL_NONCE',
        ]);

        self::assertSame('https://www.sandbox.paypal.com/payment/trustly?token=123123', $this->creator->create($this->payment, 'trustly')?->payerActionUrl());
    }

    public function test_it_keeps_no_payer_action_url_when_paypal_sends_no_such_link(): void
    {
        $this->createOrderApi->method('create')->willReturn([
            'status' => 'CREATED',
            'id' => '123123',
            'links' => [['href' => 'https://www.sandbox.paypal.com/checkoutnow?token=123123', 'rel' => 'approve', 'method' => 'GET']],
        ]);

        self::assertNull($this->creator->create($this->payment, 'paypal')?->payerActionUrl());
    }

    public function test_it_sends_paypal_payer_action_nonces_for_a_redirect_payment_source(): void
    {
        $this->createOrderApi->expects(self::once())->method('create')->with('ACCESS_TOKEN', $this->payment, 'UUID', 'trustly', 'RETURN_NONCE', 'CANCEL_NONCE')->willReturn(['status' => 'CREATED', 'id' => '123123']);

        $this->creator->create($this->payment, 'trustly');
    }

    public function test_it_sends_paypal_no_payer_action_nonce_for_a_wallet_payment_source(): void
    {
        $this->createOrderApi->expects(self::once())->method('create')->with('ACCESS_TOKEN', $this->payment, 'UUID', 'paypal', null, null)->willReturn(['status' => 'CREATED', 'id' => '123123']);

        $this->creator->create($this->payment, 'paypal');
    }
}
