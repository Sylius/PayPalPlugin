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

final readonly class ExpressOrderAddressFactory implements ExpressOrderAddressFactoryInterface
{
    /** @param AddressFactoryInterface<AddressInterface> $addressFactory */
    public function __construct(
        private AddressFactoryInterface $addressFactory,
        private ?PayPalShippingAddressFactoryInterface $shippingAddressFactory,
    ) {
    }

    public function createFromPurchaseUnit(array $purchaseUnit, ?string $phoneNumber): AddressInterface
    {
        $address = $this->addressFactory->createNew();
        $address->setPhoneNumber($phoneNumber);

        $name = explode(' ', $purchaseUnit['shipping']['name']['full_name']);
        /** @phpstan-ignore-next-line false positive */
        $address->setLastName(array_pop($name) ?? '');
        $address->setFirstName(implode(' ', $name));
        $address->setStreet($purchaseUnit['shipping']['address']['address_line_1']);
        $address->setCity($purchaseUnit['shipping']['address']['admin_area_2']);
        $address->setPostcode($purchaseUnit['shipping']['address']['postal_code']);
        $address->setCountryCode($purchaseUnit['shipping']['address']['country_code']);
        $this->applyProvince($address, (array) $purchaseUnit['shipping']['address']);

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
