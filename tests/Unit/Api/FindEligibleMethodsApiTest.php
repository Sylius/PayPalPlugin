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

namespace Tests\Sylius\PayPalPlugin\Unit\Api;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Api\FindEligibleMethodsApi;
use Sylius\PayPalPlugin\Api\FindEligibleMethodsApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class FindEligibleMethodsApiTest extends TestCase
{
    private PayPalClientInterface&MockObject $client;

    private PaymentInterface&MockObject $payment;

    private FindEligibleMethodsApi $api;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PayPalClientInterface::class);

        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress->method('getCountryCode')->willReturn('NL');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getBillingAddress')->willReturn($billingAddress);
        $order->method('getCurrencyCode')->willReturn('EUR');

        $this->payment = $this->createMock(PaymentInterface::class);
        $this->payment->method('getOrder')->willReturn($order);
        $this->payment->method('getAmount')->willReturn(12000);

        $this->api = new FindEligibleMethodsApi($this->client);
    }

    public function test_it_implements_find_eligible_methods_api_interface(): void
    {
        self::assertInstanceOf(FindEligibleMethodsApiInterface::class, $this->api);
    }

    public function test_it_asks_paypal_about_the_payers_country_amount_and_currency(): void
    {
        $this->client
            ->expects(self::once())
            ->method('post')
            ->with('v2/payments/find-eligible-methods', 'TOKEN', [
                'customer' => ['country_code' => 'NL'],
                'purchase_units' => [
                    ['amount' => ['currency_code' => 'EUR', 'value' => '120.00']],
                ],
                'preferences' => [
                    'payment_source_constraint' => [
                        'constraint_type' => 'INCLUDE',
                        'payment_sources' => ['TRUSTLY'],
                    ],
                ],
            ])
            ->willReturn(['eligible_methods' => ['trustly' => []]])
        ;

        self::assertSame(
            ['eligible_methods' => ['trustly' => []]],
            $this->api->find('TOKEN', $this->payment, ['TRUSTLY']),
        );
    }
}
