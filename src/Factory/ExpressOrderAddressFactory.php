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

namespace Sylius\PayPalPlugin\Factory;

use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;

final readonly class ExpressOrderAddressFactory implements ExpressOrderAddressFactoryInterface
{
    /** @param AddressFactoryInterface<AddressInterface> $addressFactory */
    public function __construct(
        private AddressFactoryInterface $addressFactory,
        private ?PayPalShippingAddressFactoryInterface $shippingAddressFactory,
    ) {
    }

    public function createFromPayPalOrder(PayPalOrderDetails $payPalOrder): AddressInterface
    {
        $address = $this->addressFactory->createNew();
        $address->setPhoneNumber($payPalOrder->phoneNumber());

        $name = explode(' ', (string) $payPalOrder->shippingFullName());
        /** @phpstan-ignore-next-line false positive */
        $address->setLastName(array_pop($name) ?? '');
        $address->setFirstName(implode(' ', $name));

        $shippingAddress = $payPalOrder->shippingAddress() ?? [];
        $address->setStreet($shippingAddress['address_line_1'] ?? null);
        $address->setCity($shippingAddress['admin_area_2'] ?? null);
        $address->setPostcode($shippingAddress['postal_code'] ?? null);
        $address->setCountryCode($shippingAddress['country_code'] ?? null);
        $this->applyProvince($address, $shippingAddress);

        return $address;
    }

    public function createFromCustomer(
        CustomerInterface $customer,
        ?string $countryCode,
        ?string $phoneNumber,
    ): AddressInterface {
        $address = $this->addressFactory->createNew();
        $address->setPhoneNumber($phoneNumber);
        $address->setFirstName($customer->getFirstName());
        $address->setLastName($customer->getLastName());

        $defaultAddress = $customer->getDefaultAddress();

        $address->setStreet($defaultAddress ? $defaultAddress->getStreet() : '');
        $address->setCity($defaultAddress ? $defaultAddress->getCity() : '');
        $address->setPostcode($defaultAddress ? $defaultAddress->getPostcode() : '');
        $address->setCountryCode($countryCode);

        return $address;
    }

    /** @param array<string, mixed> $payPalAddress */
    private function applyProvince(AddressInterface $address, array $payPalAddress): void
    {
        if (null === $this->shippingAddressFactory) {
            return;
        }

        $payPalShippingAddress = $this->shippingAddressFactory->create($payPalAddress);

        $address->setProvinceCode($payPalShippingAddress->getProvinceCode());
        $address->setProvinceName($payPalShippingAddress->getProvinceName());
    }
}
