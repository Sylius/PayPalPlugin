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

namespace Sylius\PayPalPlugin\Api;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Provider\PayPalRegionProvider;

final class UpdateOrderAddressApi implements UpdateOrderAddressApiInterface
{
    public function __construct(
        private PayPalClientInterface $client,
    ) {
    }

    public function update(
        string $token,
        string $orderId,
        string $referenceId,
        AddressInterface $shippingAddress,
    ): void {
        $address = [
            'address_line_1' => $shippingAddress->getStreet(),
            'admin_area_2' => $shippingAddress->getCity(),
            'postal_code' => $shippingAddress->getPostcode(),
            'country_code' => $shippingAddress->getCountryCode(),
        ];

        $region = PayPalRegionProvider::provide($shippingAddress);
        if (null !== $region) {
            $address['admin_area_1'] = $region;
        }

        $this->client->patch(
            sprintf('v2/checkout/orders/%s', $orderId),
            $token,
            [
                [
                    'op' => 'replace',
                    'path' => sprintf('/purchase_units/@reference_id==\'%s\'/shipping/address', $referenceId),
                    'value' => $address,
                ],
            ],
        );

        $this->client->patch(
            sprintf('v2/checkout/orders/%s', $orderId),
            $token,
            [
                [
                    'op' => 'replace',
                    'path' => sprintf('/purchase_units/@reference_id==\'%s\'/shipping/name', $referenceId),
                    'value' => [
                        'full_name' => $shippingAddress->getFullName(),
                    ],
                ],
            ],
        );
    }
}
