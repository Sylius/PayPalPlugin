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

namespace Sylius\PayPalPlugin\Model;

use Sylius\Component\Core\Model\AddressInterface;
use Webmozart\Assert\Assert;

readonly class PayPalPurchaseUnit
{
    public function __construct(
        private string $referenceId,
        private string $invoiceNumber,
        private string $currencyCode,
        private int $totalAmount,
        private int $shippingValue,
        private float $itemTotalValue,
        private float $taxTotalValue,
        private int $discountValue,
        private string $merchantId,
        private array $items,
        private bool $shippingRequired,
        private ?AddressInterface $shippingAddress = null,
        private string $softDescriptor = 'Sylius PayPal Payment',
        private int $shippingDiscountValue = 0,
        private ?string $customId = null,
    ) {
    }

    public function toArray(): array
    {
        $paypalPurchaseUnit = [
            'reference_id' => $this->referenceId,
            'invoice_id' => $this->invoiceNumber,
            'amount' => [
                'currency_code' => $this->currencyCode,
                'value' => number_format($this->totalAmount / 100, 2, '.', ''),
                'breakdown' => [
                    'shipping' => [
                        'currency_code' => $this->currencyCode,
                        'value' => number_format($this->shippingValue / 100, 2, '.', ''),
                    ],
                    'item_total' => [
                        'currency_code' => $this->currencyCode,
                        'value' => number_format($this->itemTotalValue, 2, '.', ''),
                    ],
                    'tax_total' => [
                        'currency_code' => $this->currencyCode,
                        'value' => number_format($this->taxTotalValue, 2, '.', ''),
                    ],
                    'discount' => [
                        'currency_code' => $this->currencyCode,
                        'value' => number_format(abs($this->discountValue) / 100, 2, '.', ''),
                    ],
                    'shipping_discount' => [
                        'currency_code' => $this->currencyCode,
                        'value' => number_format(abs($this->shippingDiscountValue) / 100, 2, '.', ''),
                    ],
                ],
            ],
            'payee' => [
                'merchant_id' => $this->merchantId,
            ],
            'soft_descriptor' => $this->softDescriptor,
            'items' => $this->items,
        ];

        if (null !== $this->customId) {
            $paypalPurchaseUnit['custom_id'] = $this->customId;
        }

        if (null !== $this->shippingAddress && $this->shippingRequired) {
            $paypalPurchaseUnit['shipping'] = $this->getShippingAddress();
        }

        return $paypalPurchaseUnit;
    }

    private function getShippingAddress(): array
    {
        Assert::isInstanceOf($this->shippingAddress, AddressInterface::class);

        return [
            'name' => ['full_name' => (string) $this->shippingAddress->getFullName()],
            'address' => [
                'address_line_1' => $this->shippingAddress->getStreet(),
                'admin_area_2' => $this->shippingAddress->getCity(),
                'postal_code' => $this->shippingAddress->getPostcode(),
                'country_code' => $this->shippingAddress->getCountryCode(),
            ],
        ];
    }
}
