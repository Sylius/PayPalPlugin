<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Model;

use Sylius\Component\Core\Model\AddressInterface;
use Webmozart\Assert\Assert;

class PayPalPurchaseUnit
{
    /** @var string */
    private $referenceId;

    /** @var string */
    private $invoiceNumber;

    /** @var string */
    private $currencyCode;

    /** @var int */
    private $totalAmount;

    /** @var int */
    private $shippingValue;

    /** @var float */
    private $itemTotalValue;

    /** @var float */
    private $taxTotalValue;

    /** @var int */
    private $discountValue;

    /** @var string */
    private $softDescriptor;

    /** @var string */
    private $merchantId;

    /** @var ?AddressInterface */
    private $shippingAddress;

    /** @var bool */
    private $shippingRequired;

    /** @var array */
    private $items;

    public function __construct(
        string $referenceId,
        string $invoiceNumber,
        string $currencyCode,
        int $totalAmount,
        int $shippingValue,
        float $itemTotalValue,
        float $taxTotalValue,
        int $discountValue,
        string $merchantId,
        array $items,
        bool $shippingRequired,
        ?AddressInterface $shippingAddress = null,
        string $softDescriptor = 'Sylius PayPal Payment'
    ) {
        $this->referenceId = $referenceId;
        $this->invoiceNumber = $invoiceNumber;
        $this->currencyCode = $currencyCode;
        $this->totalAmount = $totalAmount;
        $this->shippingValue = $shippingValue;
        $this->itemTotalValue = $itemTotalValue;
        $this->taxTotalValue = $taxTotalValue;
        $this->discountValue = $discountValue;
        $this->merchantId = $merchantId;
        $this->items = $items;
        $this->shippingRequired = $shippingRequired;
        $this->shippingAddress = $shippingAddress;
        $this->softDescriptor = $softDescriptor;
    }

    public function toArray(): array
    {
        $paypalPurchaseUnit = [
            'reference_id' => $this->referenceId,
            'invoice_number' => $this->invoiceNumber,
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
                ],
            ],
            'payee' => [
                'merchant_id' => $this->merchantId,
            ],
            'soft_descriptor' => $this->softDescriptor,
            'items' => $this->items,
        ];

        if ($this->shippingAddress !== null && $this->shippingRequired) {
            $paypalPurchaseUnit['shipping'] = $this->getShippingAddress();
        }

        return $paypalPurchaseUnit;
    }

    private function getShippingAddress(): array
    {
        Assert::isInstanceOf( $this->shippingAddress, AddressInterface::class);
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
