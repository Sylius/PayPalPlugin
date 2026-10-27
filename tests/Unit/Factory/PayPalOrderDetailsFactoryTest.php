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

namespace Tests\Sylius\PayPalPlugin\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Factory\PayPalOrderDetailsFactory;
use Sylius\PayPalPlugin\Model\PayPalOrder;

final class PayPalOrderDetailsFactoryTest extends TestCase
{
    private PayPalOrderDetailsFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new PayPalOrderDetailsFactory();
    }

    public function test_it_reads_the_order_and_its_first_purchase_unit(): void
    {
        $details = $this->factory->create($this->payPalOrder());

        self::assertSame('8VX25688WW4240157', $details->id());
        self::assertSame('COMPLETED', $details->status());
        self::assertTrue($details->isStatus('COMPLETED'));
        self::assertSame('AUTHORIZE', $details->intent());
        self::assertSame('REFERENCE', $details->referenceId());
        self::assertSame(10851, $details->amount());
        self::assertTrue($details->hasItems());
        self::assertSame(['paypal' => ['email_address' => 'buyer@example.com']], $details->paymentSource());
    }

    public function test_it_reads_the_capture_and_the_authorization_of_the_first_purchase_unit(): void
    {
        $details = $this->factory->create($this->payPalOrder());

        self::assertSame('55V81560LH563161G', $details->capture()?->id());
        self::assertSame('COMPLETED', $details->capture()->status());
        self::assertSame('0VF52814937998046', $details->authorization()?->id());
        self::assertSame('CAPTURED', $details->authorization()->status());
    }

    public function test_it_reads_the_shipping_and_the_payer_chosen_in_the_wallet(): void
    {
        $details = $this->factory->create($this->payPalOrder());

        self::assertSame('John Doe', $details->shippingFullName());
        self::assertSame(['address_line_1' => '1 Main St', 'admin_area_2' => 'San Jose', 'postal_code' => '95131', 'country_code' => 'US'], $details->shippingAddress());
        self::assertSame('DHL', $details->selectedShippingOptionId());
        self::assertSame('buyer@example.com', $details->payerEmail());
        self::assertSame('John', $details->payerGivenName());
        self::assertSame('Doe', $details->payerSurname());
        self::assertSame('US', $details->payerCountryCode());
    }

    public function test_it_prefers_the_shipping_contact_phone_number_over_the_payer_one(): void
    {
        self::assertSame('4089238291', $this->factory->create($this->payPalOrder())->phoneNumber());
    }

    public function test_it_falls_back_to_the_payer_phone_number_without_a_shipping_contact(): void
    {
        $payPalOrder = $this->payPalOrder();
        unset($payPalOrder['purchase_units'][0]['shipping']['phone_number']);

        self::assertSame('5551234', $this->factory->create($payPalOrder)->phoneNumber());
    }

    public function test_it_sums_the_amounts_of_every_purchase_unit(): void
    {
        $details = $this->factory->create(['purchase_units' => [
            ['amount' => ['currency_code' => 'USD', 'value' => '100.59']],
            ['amount' => ['currency_code' => 'USD', 'value' => '0.41']],
        ]]);

        self::assertSame(10100, $details->amount());
    }

    public function test_it_reads_nothing_from_an_order_without_details(): void
    {
        $details = $this->factory->create([]);

        self::assertSame('', $details->id());
        self::assertNull($details->status());
        self::assertSame(PayPalOrder::INTENT_CAPTURE, $details->intent());
        self::assertNull($details->referenceId());
        self::assertSame(0, $details->amount());
        self::assertFalse($details->hasItems());
        self::assertNull($details->capture());
        self::assertNull($details->authorization());
        self::assertSame([], $details->paymentSource());
        self::assertNull($details->shippingFullName());
        self::assertNull($details->shippingAddress());
        self::assertNull($details->selectedShippingOptionId());
        self::assertNull($details->payerEmail());
        self::assertNull($details->payerCountryCode());
        self::assertNull($details->phoneNumber());
    }

    public function test_it_reads_no_selected_shipping_option_when_the_buyer_picked_none(): void
    {
        $details = $this->factory->create(['purchase_units' => [['shipping' => ['options' => [
            ['id' => 'UPS', 'selected' => false],
            ['id' => '', 'selected' => true],
        ]]]]]);

        self::assertNull($details->selectedShippingOptionId());
    }

    /** @return array<string, mixed> */
    private function payPalOrder(): array
    {
        return [
            'id' => '8VX25688WW4240157',
            'intent' => 'AUTHORIZE',
            'status' => 'COMPLETED',
            'payment_source' => ['paypal' => ['email_address' => 'buyer@example.com']],
            'purchase_units' => [[
                'reference_id' => 'REFERENCE',
                'amount' => ['currency_code' => 'USD', 'value' => '108.51'],
                'items' => [['name' => 'Coral Reef Chic', 'quantity' => '1']],
                'shipping' => [
                    'name' => ['full_name' => 'John Doe'],
                    'phone_number' => ['country_code' => '1', 'national_number' => '4089238291'],
                    'address' => ['address_line_1' => '1 Main St', 'admin_area_2' => 'San Jose', 'postal_code' => '95131', 'country_code' => 'US'],
                    'options' => [
                        ['id' => 'UPS', 'selected' => false],
                        ['id' => 'DHL', 'selected' => true],
                    ],
                ],
                'payments' => [
                    'authorizations' => [['id' => '0VF52814937998046', 'status' => 'CAPTURED']],
                    'captures' => [['id' => '55V81560LH563161G', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '108.51']]],
                ],
            ]],
            'payer' => [
                'email_address' => 'buyer@example.com',
                'name' => ['given_name' => 'John', 'surname' => 'Doe'],
                'phone' => ['phone_number' => ['national_number' => '5551234']],
                'address' => ['country_code' => 'US'],
            ],
        ];
    }
}
