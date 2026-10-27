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

namespace Tests\Sylius\PayPalPlugin\Unit\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Api\CallbackSignatureVerifierInterface;
use Sylius\PayPalPlugin\Controller\ShippingCallbackAction;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Exception\ShippingMethodNotAvailableException;
use Sylius\PayPalPlugin\Factory\PayPalShippingAddressFactoryInterface;
use Sylius\PayPalPlugin\Factory\ShippingCallbackResponseFactoryInterface;
use Sylius\PayPalPlugin\Model\PayPalShippingOption;
use Sylius\PayPalPlugin\Model\PayPalShippingOptions;
use Sylius\PayPalPlugin\Provider\ChannelAvailableCountriesProviderInterface;
use Sylius\PayPalPlugin\Provider\ShippingCallbackAmountProviderInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;
use Sylius\PayPalPlugin\Resolver\ShippingOptionsResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ShippingCallbackActionTest extends TestCase
{
    private const SHIPPING_ADDRESS = [
        'country_code' => 'US',
        'admin_area_1' => 'TX',
        'admin_area_2' => 'Dallas',
        'postal_code' => '75001',
    ];

    private const PURCHASE_UNIT = [
        'reference_id' => 'REFERENCE_ID',
        'amount' => [
            'currency_code' => 'USD',
            'value' => '100.00',
            'breakdown' => [
                'item_total' => ['currency_code' => 'USD', 'value' => '90.00'],
                'tax_total' => ['currency_code' => 'USD', 'value' => '10.00'],
                'shipping' => ['currency_code' => 'USD', 'value' => '0.00'],
            ],
        ],
    ];

    private CallbackSignatureVerifierInterface&MockObject $signatureVerifier;

    private PaypalPaymentQueryInterface&MockObject $paypalPaymentQuery;

    private ChannelAvailableCountriesProviderInterface&MockObject $availableCountriesProvider;

    private PayPalShippingAddressFactoryInterface&MockObject $shippingAddressFactory;

    private ShippingOptionsResolverInterface&MockObject $shippingOptionsResolver;

    private ShippingCallbackResponseFactoryInterface&MockObject $responseFactory;

    private ShippingCallbackAmountProviderInterface&MockObject $amountProvider;

    private OrderInterface&MockObject $order;

    private PaymentInterface&MockObject $payment;

    private ShippingCallbackAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signatureVerifier = $this->createMock(CallbackSignatureVerifierInterface::class);
        $this->signatureVerifier->method('verify')->willReturn(true);
        $this->paypalPaymentQuery = $this->createMock(PaypalPaymentQueryInterface::class);
        $this->availableCountriesProvider = $this->createMock(ChannelAvailableCountriesProviderInterface::class);
        $this->shippingAddressFactory = $this->createMock(PayPalShippingAddressFactoryInterface::class);
        $this->shippingOptionsResolver = $this->createMock(ShippingOptionsResolverInterface::class);
        $this->responseFactory = $this->createMock(ShippingCallbackResponseFactoryInterface::class);
        $this->amountProvider = $this->createMock(ShippingCallbackAmountProviderInterface::class);

        $this->order = $this->createMock(OrderInterface::class);
        $this->order->method('getChannel')->willReturn($this->createMock(ChannelInterface::class));

        $this->payment = $this->createMock(PaymentInterface::class);
        $this->payment->method('getOrder')->willReturn($this->order);
        $this->paypalPaymentQuery
            ->method('getForUpdateByOrderId')
            ->willReturnCallback(fn (string $id): ?PaymentInterface => 'PAYPAL_ORDER_ID' === $id ? $this->payment : null);

        $this->action = new ShippingCallbackAction(
            $this->signatureVerifier,
            $this->paypalPaymentQuery,
            $this->availableCountriesProvider,
            $this->shippingAddressFactory,
            $this->shippingOptionsResolver,
            $this->responseFactory,
            $this->amountProvider,
        );
    }

    public function test_it_answers_with_the_response_its_factory_built_for_the_resolved_options_and_the_recalculated_amount(): void
    {
        $this->availableCountriesProvider->method('provideForChannel')->willReturn(['US', 'CA']);
        $this->shippingAddressFactory
            ->expects(self::once())
            ->method('create')
            ->with(self::SHIPPING_ADDRESS)
            ->willReturn($address = $this->createMock(AddressInterface::class));
        $this->shippingOptionsResolver
            ->expects(self::once())
            ->method('resolve')
            ->with($this->order, $address)
            ->willReturn(self::shippingOptions());
        $this->amountProvider
            ->expects(self::once())
            ->method('provide')
            ->with($this->payment, $address, self::shippingOptions()->selected())
            ->willReturn(['value' => '107.00']);
        $this->responseFactory
            ->expects(self::once())
            ->method('create')
            ->with('PAYPAL_ORDER_ID', self::PURCHASE_UNIT, ['value' => '107.00'], self::shippingOptions())
            ->willReturn(['id' => 'PAYPAL_ORDER_ID', 'purchase_units' => ['RESPONSE_UNIT']]);

        $response = ($this->action)($this->callbackRequest());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(
            ['id' => 'PAYPAL_ORDER_ID', 'purchase_units' => ['RESPONSE_UNIT']],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function test_it_prices_the_option_the_buyer_picked_in_the_wallet(): void
    {
        $picked = new PayPalShippingOption('dhl', 'DHL', 'USD', 2550, true);
        $this->availableCountriesProvider->method('provideForChannel')->willReturn(['US']);
        $this->shippingAddressFactory->method('create')->willReturn($address = $this->createMock(AddressInterface::class));
        $this->shippingOptionsResolver->method('resolve')->willReturn(self::twoShippingOptions());
        $this->amountProvider->expects(self::once())->method('provide')->with($this->payment, $address, $picked)->willReturn(['value' => '132.50']);
        $this->responseFactory->expects(self::once())->method('create')->with('PAYPAL_ORDER_ID', self::PURCHASE_UNIT, ['value' => '132.50'], self::twoShippingOptions()->withSelected('dhl'))->willReturn([]);

        ($this->action)($this->callbackRequest(['shipping_option' => ['id' => 'dhl', 'label' => 'DHL', 'type' => 'SHIPPING']]));
    }

    public function test_it_keeps_the_resolved_selection_when_the_picked_option_is_not_offered(): void
    {
        $this->availableCountriesProvider->method('provideForChannel')->willReturn(['US']);
        $this->shippingAddressFactory->method('create')->willReturn($address = $this->createMock(AddressInterface::class));
        $this->shippingOptionsResolver->method('resolve')->willReturn(self::twoShippingOptions());
        $this->amountProvider->expects(self::once())->method('provide')->with($this->payment, $address, self::twoShippingOptions()->selected())->willReturn([]);
        $this->responseFactory->method('create')->willReturn([]);

        ($this->action)($this->callbackRequest(['shipping_option' => ['id' => 'fedex']]));
    }

    public function test_it_refuses_an_option_the_order_cannot_be_priced_with(): void
    {
        $this->availableCountriesProvider->method('provideForChannel')->willReturn(['US']);
        $this->shippingAddressFactory->method('create')->willReturn($this->createMock(AddressInterface::class));
        $this->shippingOptionsResolver->method('resolve')->willReturn(self::shippingOptions());
        $this->amountProvider->method('provide')->willThrowException(ShippingMethodNotAvailableException::withCode('ups'));
        $this->responseFactory->expects(self::never())->method('create');

        self::assertUnprocessableWithIssue('METHOD_UNAVAILABLE', ($this->action)($this->callbackRequest()));
    }

    public function test_it_refuses_a_country_the_channel_does_not_sell_to(): void
    {
        $this->availableCountriesProvider->method('provideForChannel')->willReturn(['CA']);
        $this->shippingOptionsResolver->expects(self::never())->method('resolve');

        $response = ($this->action)($this->callbackRequest());

        self::assertUnprocessableWithIssue('COUNTRY_ERROR', $response);
    }

    public function test_it_refuses_an_address_nothing_can_be_shipped_to(): void
    {
        $this->availableCountriesProvider->method('provideForChannel')->willReturn(['US']);
        $this->shippingOptionsResolver->method('resolve')->willReturn(new PayPalShippingOptions());
        $this->amountProvider->expects(self::never())->method('provide');

        $response = ($this->action)($this->callbackRequest());

        self::assertUnprocessableWithIssue('ADDRESS_ERROR', $response);
    }

    public function test_it_refuses_an_order_id_it_does_not_know(): void
    {
        $this->availableCountriesProvider->expects(self::never())->method('provideForChannel');

        $response = ($this->action)($this->callbackRequest(['id' => 'UNKNOWN']));

        self::assertUnprocessableWithIssue('ADDRESS_ERROR', $response);
    }

    public function test_it_refuses_an_order_whose_payment_lookup_fails(): void
    {
        $paypalPaymentQuery = $this->createMock(PaypalPaymentQueryInterface::class);
        $paypalPaymentQuery->method('getForUpdateByOrderId')->willThrowException(new PaymentNotFoundException());

        $action = new ShippingCallbackAction(
            $this->signatureVerifier,
            $paypalPaymentQuery,
            $this->availableCountriesProvider,
            $this->shippingAddressFactory,
            $this->shippingOptionsResolver,
            $this->responseFactory,
            $this->amountProvider,
        );

        self::assertUnprocessableWithIssue('ADDRESS_ERROR', ($action)($this->callbackRequest()));
    }

    public function test_it_refuses_a_body_that_is_not_json(): void
    {
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], 'not json');

        self::assertUnprocessableWithIssue('ADDRESS_ERROR', ($this->action)($request));
    }

    /** @param array<string, mixed> $overrides */
    private function callbackRequest(array $overrides = []): Request
    {
        $payload = $overrides + [
            'id' => 'PAYPAL_ORDER_ID',
            'shipping_address' => self::SHIPPING_ADDRESS,
            'purchase_units' => [self::PURCHASE_UNIT],
        ];

        return new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode($payload));
    }

    private static function shippingOptions(): PayPalShippingOptions
    {
        return new PayPalShippingOptions(new PayPalShippingOption('ups', 'UPS', 'USD', 1000, true));
    }

    private static function twoShippingOptions(): PayPalShippingOptions
    {
        return new PayPalShippingOptions(new PayPalShippingOption('ups', 'UPS', 'USD', 1000, true), new PayPalShippingOption('dhl', 'DHL', 'USD', 2550));
    }

    private static function assertUnprocessableWithIssue(string $issue, Response $response): void
    {
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame(
            ['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => $issue]]],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function test_it_answers_not_found_when_the_request_is_not_signed_by_paypal(): void
    {
        $signatureVerifier = $this->createMock(CallbackSignatureVerifierInterface::class);
        $signatureVerifier->method('verify')->willReturn(false);

        $this->paypalPaymentQuery->expects(self::never())->method('getForUpdateByOrderId');
        $this->shippingOptionsResolver->expects(self::never())->method('resolve');
        $this->responseFactory->expects(self::never())->method('create');

        $action = new ShippingCallbackAction(
            $signatureVerifier,
            $this->paypalPaymentQuery,
            $this->availableCountriesProvider,
            $this->shippingAddressFactory,
            $this->shippingOptionsResolver,
            $this->responseFactory,
            $this->amountProvider,
        );

        $response = ($action)($this->callbackRequest());

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame(['error' => 'Not found'], json_decode((string) $response->getContent(), true));
    }
}
