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
use Sylius\Component\Addressing\Model\ProvinceInterface;
use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\PayPalPlugin\Resolver\PayPalShippingAddressResolver;
use Sylius\PayPalPlugin\Resolver\PayPalShippingAddressResolverInterface;

final class PayPalShippingAddressResolverTest extends TestCase
{
    private AddressFactoryInterface&MockObject $addressFactory;

    private RepositoryInterface&MockObject $provinceRepository;

    private PayPalShippingAddressResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->addressFactory = $this->createMock(AddressFactoryInterface::class);
        $this->provinceRepository = $this->createMock(RepositoryInterface::class);
        $this->addressFactory->method('createNew')->willReturnCallback(fn (): Address => new Address());

        $this->resolver = new PayPalShippingAddressResolver($this->addressFactory, $this->provinceRepository);
    }

    public function test_it_implements_paypal_shipping_address_resolver_interface(): void
    {
        self::assertInstanceOf(PayPalShippingAddressResolverInterface::class, $this->resolver);
    }

    public function test_it_maps_the_redacted_address_paypal_sends(): void
    {
        $this->provinceRepository->method('findOneBy')->willReturn(null);

        $address = $this->resolver->resolve([
            'country_code' => 'US',
            'admin_area_1' => 'TX',
            'admin_area_2' => 'Dallas',
            'postal_code' => '75001',
        ]);

        self::assertSame('US', $address->getCountryCode());
        self::assertSame('Dallas', $address->getCity());
        self::assertSame('75001', $address->getPostcode());
    }

    public function test_it_prefixes_the_region_with_the_country_when_that_province_is_known(): void
    {
        $province = $this->createMock(ProvinceInterface::class);
        $this->provinceRepository->expects(self::once())->method('findOneBy')->with(['code' => 'US-TX'])->willReturn($province);

        $address = $this->resolver->resolve(['country_code' => 'US', 'admin_area_1' => 'TX']);

        self::assertSame('US-TX', $address->getProvinceCode());
    }

    public function test_it_falls_back_to_the_bare_region_when_only_that_province_is_known(): void
    {
        $province = $this->createMock(ProvinceInterface::class);
        $this->provinceRepository
            ->method('findOneBy')
            ->willReturnCallback(fn (array $criteria): ?ProvinceInterface => $criteria === ['code' => 'TX'] ? $province : null);

        $address = $this->resolver->resolve(['country_code' => 'US', 'admin_area_1' => 'TX']);

        self::assertSame('TX', $address->getProvinceCode());
    }

    public function test_it_keeps_a_region_that_matches_no_known_province_as_free_text(): void
    {
        $this->provinceRepository->method('findOneBy')->willReturn(null);

        $address = $this->resolver->resolve(['country_code' => 'PL', 'admin_area_1' => 'Mazowieckie']);

        self::assertNull($address->getProvinceCode());
        self::assertSame('Mazowieckie', $address->getProvinceName());
    }

    public function test_it_leaves_the_region_name_out_once_it_resolves_to_a_province(): void
    {
        $this->provinceRepository->method('findOneBy')->willReturn($this->createMock(ProvinceInterface::class));

        $address = $this->resolver->resolve(['country_code' => 'US', 'admin_area_1' => 'TX']);

        self::assertSame('US-TX', $address->getProvinceCode());
        self::assertNull($address->getProvinceName());
    }

    public function test_it_leaves_out_the_parts_paypal_did_not_send(): void
    {
        $this->provinceRepository->expects(self::never())->method('findOneBy');

        $address = $this->resolver->resolve(['country_code' => 'DE']);

        self::assertSame('DE', $address->getCountryCode());
        self::assertNull($address->getCity());
        self::assertNull($address->getPostcode());
        self::assertNull($address->getProvinceCode());
        self::assertNull($address->getProvinceName());
    }

    public function test_it_treats_blank_values_as_absent(): void
    {
        $this->provinceRepository->expects(self::never())->method('findOneBy');

        $address = $this->resolver->resolve([
            'country_code' => 'DE',
            'admin_area_1' => '   ',
            'admin_area_2' => '',
            'postal_code' => ' ',
        ]);

        self::assertNull($address->getCity());
        self::assertNull($address->getPostcode());
        self::assertNull($address->getProvinceCode());
        self::assertNull($address->getProvinceName());
    }
}
