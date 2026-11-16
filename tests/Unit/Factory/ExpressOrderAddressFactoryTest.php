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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\PayPalPlugin\Factory\ExpressOrderAddressFactory;
use Sylius\PayPalPlugin\Factory\ExpressOrderAddressFactoryInterface;
use Sylius\PayPalPlugin\Factory\PayPalShippingAddressFactoryInterface;
use Sylius\PayPalPlugin\Mapper\PayPalOrderDetailsMapper;
use Sylius\PayPalPlugin\Model\PayPalAddress;
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;

final class ExpressOrderAddressFactoryTest extends TestCase
{
    private AddressFactoryInterface&MockObject $addressFactory;

    private PayPalShippingAddressFactoryInterface&MockObject $shippingAddressFactory;

    private ExpressOrderAddressFactory $factory;

    private ExpressOrderAddressFactory $factoryWithoutProvinces;

    protected function setUp(): void
    {
        parent::setUp();
        $this->addressFactory = $this->createMock(AddressFactoryInterface::class);
        $this->addressFactory->method('createNew')->willReturnCallback(fn (): Address => new Address());
        $this->shippingAddressFactory = $this->createMock(PayPalShippingAddressFactoryInterface::class);

        $this->factory = new ExpressOrderAddressFactory($this->addressFactory, $this->shippingAddressFactory);
        $this->factoryWithoutProvinces = new ExpressOrderAddressFactory($this->addressFactory, null);
    }

    public function test_it_implements_express_order_address_factory_interface(): void
    {
        self::assertInstanceOf(ExpressOrderAddressFactoryInterface::class, $this->factory);
    }

    public function test_it_builds_the_address_from_the_shipping_paypal_echoes(): void
    {
        $this->shippingAddressFactory->method('create')->willReturn(new Address());

        $address = $this->factory->createFromPayPalOrder($this->payPalOrder(phoneNumber: '15551234567'));

        self::assertSame('Oliver', $address->getFirstName());
        self::assertSame('Queen', $address->getLastName());
        self::assertSame('1 Star City Plaza', $address->getStreet());
        self::assertSame('Star City', $address->getCity());
        self::assertSame('10001', $address->getPostcode());
        self::assertSame('US', $address->getCountryCode());
        self::assertSame('15551234567', $address->getPhoneNumber());
    }

    public function test_it_puts_every_name_but_the_last_in_the_first_name(): void
    {
        $this->shippingAddressFactory->method('create')->willReturn(new Address());

        $address = $this->factory->createFromPayPalOrder($this->payPalOrder('Mary Jane Watson Parker'));

        self::assertSame('Mary Jane Watson', $address->getFirstName());
        self::assertSame('Parker', $address->getLastName());
    }

    public function test_it_keeps_a_one_word_name_as_the_last_name(): void
    {
        $this->shippingAddressFactory->method('create')->willReturn(new Address());

        $address = $this->factory->createFromPayPalOrder($this->payPalOrder('Cher'));

        self::assertSame('', $address->getFirstName());
        self::assertSame('Cher', $address->getLastName());
    }

    public function test_it_leaves_the_phone_number_empty_when_paypal_sends_none(): void
    {
        $this->shippingAddressFactory->method('create')->willReturn(new Address());

        $address = $this->factory->createFromPayPalOrder($this->payPalOrder());

        self::assertNull($address->getPhoneNumber());
    }

    public function test_it_stores_the_region_resolved_from_the_paypal_address(): void
    {
        $resolved = new Address();
        $resolved->setProvinceCode('US-TX');

        $this->shippingAddressFactory
            ->expects(self::once())
            ->method('create')
            ->with(new PayPalAddress('1 Star City Plaza', 'TX', 'Star City', '10001', 'US'))
            ->willReturn($resolved)
        ;

        $address = $this->factory->createFromPayPalOrder($this->payPalOrder(adminArea1: 'TX'));

        self::assertSame('US-TX', $address->getProvinceCode());
        self::assertNull($address->getProvinceName());
    }

    public function test_it_keeps_an_unresolved_region_as_a_province_name(): void
    {
        $resolved = new Address();
        $resolved->setProvinceName('Nowhere County');

        $this->shippingAddressFactory->method('create')->willReturn($resolved);

        $address = $this->factory->createFromPayPalOrder($this->payPalOrder(adminArea1: 'Nowhere County'));

        self::assertNull($address->getProvinceCode());
        self::assertSame('Nowhere County', $address->getProvinceName());
    }

    public function test_it_leaves_the_region_out_when_it_has_no_shipping_address_factory(): void
    {
        $address = $this->factoryWithoutProvinces->createFromPayPalOrder($this->payPalOrder(adminArea1: 'TX'));

        self::assertNull($address->getProvinceCode());
        self::assertNull($address->getProvinceName());
        self::assertSame('1 Star City Plaza', $address->getStreet());
        self::assertSame('Star City', $address->getCity());
        self::assertSame('10001', $address->getPostcode());
        self::assertSame('US', $address->getCountryCode());
    }

    public function test_it_keeps_the_region_when_paypal_sends_no_country_code(): void
    {
        $resolved = new Address();
        $resolved->setProvinceCode('TX');

        $this->shippingAddressFactory->method('create')->willReturn($resolved);

        $address = $this->factory->createFromPayPalOrder($this->payPalOrder(adminArea1: 'TX', countryCode: null));

        self::assertSame('TX', $address->getProvinceCode());
    }

    public function test_it_returns_a_new_address_on_every_call(): void
    {
        $this->shippingAddressFactory->method('create')->willReturn(new Address());

        $first = $this->factory->createFromPayPalOrder($this->payPalOrder());
        $second = $this->factory->createFromPayPalOrder($this->payPalOrder());

        self::assertNotSame($first, $second);
    }

    public function test_it_builds_the_address_from_the_customer_and_their_default_address(): void
    {
        $defaultAddress = new Address();
        $defaultAddress->setStreet('1 Main St');
        $defaultAddress->setCity('Dallas');
        $defaultAddress->setPostcode('75001');

        $address = $this->factory->createFromCustomer($this->customer($defaultAddress), 'US', '15551234567');

        self::assertSame('Oliver', $address->getFirstName());
        self::assertSame('Queen', $address->getLastName());
        self::assertSame('1 Main St', $address->getStreet());
        self::assertSame('Dallas', $address->getCity());
        self::assertSame('75001', $address->getPostcode());
        self::assertSame('US', $address->getCountryCode());
        self::assertSame('15551234567', $address->getPhoneNumber());
    }

    public function test_it_writes_the_empty_strings_the_checkout_reads_as_a_missing_billing_address(): void
    {
        $address = $this->factory->createFromCustomer($this->customer(null), 'US', null);

        self::assertSame('', $address->getStreet());
        self::assertSame('', $address->getCity());
        self::assertSame('', $address->getPostcode());
    }

    public function test_it_does_not_resolve_a_region_for_an_order_that_needs_no_shipping(): void
    {
        $this->shippingAddressFactory->expects(self::never())->method('create');

        $address = $this->factory->createFromCustomer($this->customer(null), 'US', null);

        self::assertNull($address->getProvinceCode());
        self::assertNull($address->getProvinceName());
    }

    public function test_it_leaves_the_customer_address_phone_number_empty_when_paypal_sends_none(): void
    {
        $address = $this->factory->createFromCustomer($this->customer(null), 'US', null);

        self::assertNull($address->getPhoneNumber());
    }

    private function payPalOrder(
        string $fullName = 'Oliver Queen',
        ?string $adminArea1 = null,
        ?string $phoneNumber = null,
        ?string $countryCode = 'US',
    ): PayPalOrderDetails {
        $address = [
            'address_line_1' => '1 Star City Plaza',
            'admin_area_2' => 'Star City',
            'postal_code' => '10001',
            'country_code' => $countryCode,
        ];

        if (null !== $adminArea1) {
            $address['admin_area_1'] = $adminArea1;
        }

        $shipping = ['name' => ['full_name' => $fullName], 'address' => $address];

        if (null !== $phoneNumber) {
            $shipping['phone_number'] = ['country_code' => '1', 'national_number' => $phoneNumber];
        }

        return (new PayPalOrderDetailsMapper())->map(['purchase_units' => [['shipping' => $shipping]]]);
    }

    private function customer(?AddressInterface $defaultAddress): CustomerInterface&MockObject
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getFirstName')->willReturn('Oliver');
        $customer->method('getLastName')->willReturn('Queen');
        $customer->method('getDefaultAddress')->willReturn($defaultAddress);

        return $customer;
    }
}
