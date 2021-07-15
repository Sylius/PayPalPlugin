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

use Sylius\Component\Core\Model\OrderInterface;

class PayPalOrder
{
    const NO_SHIPPING = 'NO_SHIPPING';

    const PROVIDED_ADDRESS = 'SET_PROVIDED_ADDRESS';

    const PAYPAL_ADDRESS = 'GET_FROM_FILE';

    /** @var string */
    private $intent;

    /** @var PayPalPurchaseUnit */
    private $payPalPurchaseUnit;

    /** @var OrderInterface */
    private $order;

    public function __construct(OrderInterface $order, PayPalPurchaseUnit $payPalPurchaseUnit, string $intent)
    {
        $this->payPalPurchaseUnit = $payPalPurchaseUnit;
        $this->order = $order;
        $this->intent = $intent;
    }

    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'purchase_units' => [
                $this->payPalPurchaseUnit->toArray(),
            ],
            'application_context' => [
                'shipping_preference' => $this->getShippingPreference()
            ]
        ];
    }

    private function getShippingPreference(): string
    {
        if ($this->order->isShippingRequired()) {
            if ($this->order->getShippingAddress() !== null) {
                return self::PROVIDED_ADDRESS;
            }

            return self::PAYPAL_ADDRESS;
        }

        return self::NO_SHIPPING;
    }
}
