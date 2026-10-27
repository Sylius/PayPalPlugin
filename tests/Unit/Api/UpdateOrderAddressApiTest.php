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
use Sylius\PayPalPlugin\Api\UpdateOrderAddressApi;
use Sylius\PayPalPlugin\Api\UpdateOrderAddressApiInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class UpdateOrderAddressApiTest extends TestCase
{
    private PayPalClientInterface&MockObject $client;

    private AddressInterface&MockObject $shippingAddress;

    private UpdateOrderAddressApi $updateOrderAddressApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PayPalClientInterface::class);
        $this->shippingAddress = $this->createMock(AddressInterface::class);
        $this->updateOrderAddressApi = new UpdateOrderAddressApi($this->client);
    }

    public function test_it_implements_update_order_address_api_interface(): void
    {
        self::assertInstanceOf(UpdateOrderAddressApiInterface::class, $this->updateOrderAddressApi);
    }

    public function test_it_replaces_the_shipping_address_and_the_shipping_name_of_the_purchase_unit(): void
    {
        $this->shippingAddress->method('getFullName')->willReturn('Oliver Queen');
        $this->shippingAddress->method('getStreet')->willReturn('1 Main St');
        $this->shippingAddress->method('getCity')->willReturn('Dallas');
        $this->shippingAddress->method('getPostcode')->willReturn('75001');
        $this->shippingAddress->method('getCountryCode')->willReturn('US');
        $this->shippingAddress->method('getProvinceCode')->willReturn('US-TX');

        $patches = [];
        $this->client->expects(self::exactly(2))->method('patch')->willReturnCallback(
            function (string $url, string $token, ?array $data = null) use (&$patches): array {
                self::assertSame('v2/checkout/orders/ORDER_ID', $url);
                self::assertSame('TOKEN', $token);
                $patches[] = $data[0];

                return [];
            },
        );

        $this->updateOrderAddressApi->update('TOKEN', 'ORDER_ID', 'REFERENCE_ID', $this->shippingAddress);

        self::assertSame([
            'op' => 'replace',
            'path' => '/purchase_units/@reference_id==\'REFERENCE_ID\'/shipping/address',
            'value' => [
                'address_line_1' => '1 Main St',
                'admin_area_2' => 'Dallas',
                'postal_code' => '75001',
                'country_code' => 'US',
                'admin_area_1' => 'TX',
            ],
        ], $patches[0]);

        self::assertSame([
            'op' => 'replace',
            'path' => '/purchase_units/@reference_id==\'REFERENCE_ID\'/shipping/name',
            'value' => ['full_name' => 'Oliver Queen'],
        ], $patches[1]);
    }

    public function test_it_leaves_the_region_out_when_the_address_carries_neither(): void
    {
        $this->shippingAddress->method('getCountryCode')->willReturn('US');

        self::assertArrayNotHasKey('admin_area_1', $this->replacedAddress());
    }

    /** @return array<string, mixed> */
    private function replacedAddress(): array
    {
        $address = [];
        $this->client->method('patch')->willReturnCallback(
            function (string $url, string $token, ?array $data = null) use (&$address): array {
                if (str_ends_with((string) $data[0]['path'], '/shipping/address')) {
                    $address = $data[0]['value'];
                }

                return [];
            },
        );

        $this->updateOrderAddressApi->update('TOKEN', 'ORDER_ID', 'REFERENCE_ID', $this->shippingAddress);

        return $address;
    }
}
