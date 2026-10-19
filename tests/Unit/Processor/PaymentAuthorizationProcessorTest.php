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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\AuthorizeOrderApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiConnectionException;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Exception\PayPalApiServerException;
use Sylius\PayPalPlugin\Processor\PaymentAuthorizationProcessor;
use Sylius\PayPalPlugin\Processor\PaymentAuthorizationProcessorInterface;
use Sylius\PayPalPlugin\Updater\ApprovedPayPalOrderUpdaterInterface;

final class PaymentAuthorizationProcessorTest extends TestCase
{
    private const DETAILS = [
        'status' => 'CAPTURED',
        'paypal_order_id' => '123123',
        'reference_id' => 'REFERENCE_ID',
        'payment_source' => 'card',
    ];

    private const ORDER_DETAILS = [
        'status' => 'COMPLETED',
        'id' => '123123',
        'purchase_units' => [['reference_id' => 'REFERENCE_ID', 'payments' => ['authorizations' => [['id' => 'AUTHORIZATION_ID', 'status' => 'CREATED']]]]],
    ];

    private ApprovedPayPalOrderUpdaterInterface&MockObject $approvedPayPalOrderUpdater;

    private AuthorizeOrderApiInterface&MockObject $authorizeOrderApi;

    private OrderDetailsApiInterface&MockObject $orderDetailsApi;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private PaymentInterface&MockObject $payment;

    private PaymentAuthorizationProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->approvedPayPalOrderUpdater = $this->createMock(ApprovedPayPalOrderUpdaterInterface::class);
        $this->authorizeOrderApi = $this->createMock(AuthorizeOrderApiInterface::class);
        $this->orderDetailsApi = $this->createMock(OrderDetailsApiInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->payment = $this->createMock(PaymentInterface::class);

        $this->payment->method('getMethod')->willReturn($this->paymentMethod);
        $this->payment->method('getDetails')->willReturn(self::DETAILS);

        $this->processor = new PaymentAuthorizationProcessor(
            $this->approvedPayPalOrderUpdater,
            $this->authorizeOrderApi,
            $this->orderDetailsApi,
        );
    }

    public function test_it_implements_payment_authorization_processor_interface(): void
    {
        self::assertInstanceOf(PaymentAuthorizationProcessorInterface::class, $this->processor);
    }

    public function test_it_authorizes_the_paypal_order_and_records_the_authorization_on_the_payment(): void
    {
        $this->authorizeOrderApi->expects(self::once())->method('authorize')->with($this->paymentMethod, '123123', null);
        $this->orderDetailsApi->method('get')->with($this->paymentMethod, '123123')->willReturn(self::ORDER_DETAILS);
        $this->payment->expects(self::once())->method('setDetails')->with([
            'status' => 'AUTHORIZED',
            'paypal_order_id' => '123123',
            'reference_id' => 'REFERENCE_ID',
            'payment_source' => 'card',
            'authorization_id' => 'AUTHORIZATION_ID',
            'authorization_status' => 'CREATED',
        ]);

        self::assertSame(self::ORDER_DETAILS, $this->processor->authorize($this->payment));
    }

    public function test_it_brings_the_approved_paypal_order_up_to_date_before_authorizing_it(): void
    {
        $this->orderDetailsApi->method('get')->willReturn(self::ORDER_DETAILS);

        $this->approvedPayPalOrderUpdater->expects(self::once())->method('update')->with($this->payment);
        $this->authorizeOrderApi->expects(self::once())->method('authorize');

        $this->processor->authorize($this->payment);
    }

    public function test_it_authorizes_the_paypal_order_under_the_request_id_given_by_the_caller(): void
    {
        $this->orderDetailsApi->method('get')->willReturn(self::ORDER_DETAILS);

        $this->authorizeOrderApi->expects(self::once())->method('authorize')->with($this->paymentMethod, '123123', 'PAYMENT_REQUEST_HASH-authorize');

        $this->processor->authorize($this->payment, 'PAYMENT_REQUEST_HASH-authorize');
    }

    public function test_it_records_an_authorization_paypal_has_not_created_yet_as_processing(): void
    {
        $this->orderDetailsApi->method('get')->willReturn(self::orderDetailsWithAuthorization(['id' => 'AUTHORIZATION_ID', 'status' => 'PENDING']));

        $this->payment->expects(self::once())->method('setDetails')->with(self::callback(
            static fn (array $details): bool => 'PROCESSING' === $details['status'] && 'PENDING' === $details['authorization_status'],
        ));

        $this->processor->authorize($this->payment);
    }

    public function test_it_leaves_the_payment_details_alone_when_paypal_authorized_nothing(): void
    {
        $notAuthorized = ['status' => 'APPROVED', 'id' => '123123', 'purchase_units' => [['reference_id' => 'REFERENCE_ID']]];
        $this->orderDetailsApi->method('get')->willReturn($notAuthorized);

        $this->payment->expects(self::never())->method('setDetails');

        self::assertSame($notAuthorized, $this->processor->authorize($this->payment));
    }

    public function test_it_reads_the_order_paypal_could_not_answer_the_authorization_of(): void
    {
        $this->authorizeOrderApi->method('authorize')->willThrowException(new PayPalApiServerException('POST', 'v2/checkout/orders/123123/authorize', 503));
        $this->orderDetailsApi->expects(self::once())->method('get')->willReturn(self::ORDER_DETAILS);

        self::assertSame(self::ORDER_DETAILS, $this->processor->authorize($this->payment));
    }

    public function test_it_reads_the_order_paypal_could_not_be_reached_to_authorize(): void
    {
        $this->authorizeOrderApi->method('authorize')->willThrowException(new PayPalApiConnectionException('POST', 'v2/checkout/orders/123123/authorize'));
        $this->orderDetailsApi->expects(self::once())->method('get')->willReturn(self::ORDER_DETAILS);

        self::assertSame(self::ORDER_DETAILS, $this->processor->authorize($this->payment));
    }

    #[DataProvider('issuesLeavingTheOrderToRead')]
    public function test_it_reads_the_order_paypal_refused_to_authorize_for_its_state(string $issue): void
    {
        $this->authorizeOrderApi->method('authorize')->willThrowException(new PayPalApiRequestException('POST', 'v2/checkout/orders/123123/authorize', 422, 'UNPROCESSABLE_ENTITY', issues: [$issue]));
        $this->orderDetailsApi->expects(self::once())->method('get')->willReturn(self::ORDER_DETAILS);

        self::assertSame(self::ORDER_DETAILS, $this->processor->authorize($this->payment));
    }

    /** @return iterable<string, array{string}> */
    public static function issuesLeavingTheOrderToRead(): iterable
    {
        yield 'already authorized' => ['ORDER_ALREADY_AUTHORIZED'];
        yield 'not approved' => ['ORDER_NOT_APPROVED'];
        yield 'payer action required' => ['PAYER_ACTION_REQUIRED'];
    }

    public function test_it_lets_an_authorization_paypal_refused_for_the_payer_through(): void
    {
        $declined = new PayPalApiRequestException('POST', 'v2/checkout/orders/123123/authorize', 422, 'UNPROCESSABLE_ENTITY', issues: ['INSTRUMENT_DECLINED']);
        $this->authorizeOrderApi->method('authorize')->willThrowException($declined);

        $this->orderDetailsApi->expects(self::never())->method('get');

        $this->expectExceptionObject($declined);

        $this->processor->authorize($this->payment);
    }

    /**
     * @param array<string, mixed> $authorization
     *
     * @return array<string, mixed>
     */
    private static function orderDetailsWithAuthorization(array $authorization): array
    {
        return [
            'status' => 'COMPLETED',
            'id' => '123123',
            'purchase_units' => [['reference_id' => 'REFERENCE_ID', 'payments' => ['authorizations' => [$authorization]]]],
        ];
    }
}
