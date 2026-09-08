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

namespace Sylius\PayPalPlugin\Resolver;

use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final readonly class PayPalShippingAddressResolver implements PayPalShippingAddressResolverInterface
{
    /** @param AddressFactoryInterface<AddressInterface> $addressFactory */
    public function __construct(
        private AddressFactoryInterface $addressFactory,
        private RepositoryInterface $provinceRepository,
    ) {
    }

    public function resolve(array $payPalShippingAddress): AddressInterface
    {
        $countryCode = $this->stringOrNull($payPalShippingAddress['country_code'] ?? null);

        /** @var AddressInterface $address */
        $address = $this->addressFactory->createNew();
        $address->setCountryCode($countryCode);
        $address->setCity($this->stringOrNull($payPalShippingAddress['admin_area_2'] ?? null));
        $address->setPostcode($this->stringOrNull($payPalShippingAddress['postal_code'] ?? null));
        $adminArea1 = $this->stringOrNull($payPalShippingAddress['admin_area_1'] ?? null);
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

    private function stringOrNull(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
