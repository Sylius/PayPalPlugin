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
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\PayPalPlugin\Model\PayPalAddress;

final readonly class PayPalShippingAddressFactory implements PayPalShippingAddressFactoryInterface
{
    /** @param AddressFactoryInterface<AddressInterface> $addressFactory */
    public function __construct(
        private AddressFactoryInterface $addressFactory,
        private RepositoryInterface $provinceRepository,
    ) {
    }

    public function create(PayPalAddress $payPalShippingAddress): AddressInterface
    {
        $countryCode = $payPalShippingAddress->countryCode();

        /** @var AddressInterface $address */
        $address = $this->addressFactory->createNew();
        $address->setCountryCode($countryCode);
        $address->setCity($payPalShippingAddress->adminArea2());
        $address->setPostcode($payPalShippingAddress->postalCode());
        $adminArea1 = $payPalShippingAddress->adminArea1();
        $provinceCode = $this->resolveProvinceCode($countryCode, $adminArea1);

        $address->setProvinceCode($provinceCode);
        $address->setProvinceName(null === $provinceCode ? $adminArea1 : null);

        return $address;
    }

    private function resolveProvinceCode(?string $countryCode, ?string $adminArea1): ?string
    {
        if (null === $adminArea1) {
            return null;
        }

        $candidates = null !== $countryCode ? [$countryCode . '-' . $adminArea1, $adminArea1] : [$adminArea1];

        foreach ($candidates as $candidate) {
            if (null !== $this->provinceRepository->findOneBy(['code' => $candidate])) {
                return $candidate;
            }
        }

        return null;
    }
}
