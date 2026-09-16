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

use Sylius\Component\Core\Model\OrderInterface;

class PayPalOrder
{
    public const INTENT_CAPTURE = 'CAPTURE';

    public const NO_SHIPPING = 'NO_SHIPPING';

    public const PROVIDED_ADDRESS = 'SET_PROVIDED_ADDRESS';

    public const PAYPAL_ADDRESS = 'GET_FROM_FILE';

    public const USER_ACTION_PAY_NOW = 'PAY_NOW';

    public const PAYMENT_METHOD_PREFERENCE_IMMEDIATE = 'IMMEDIATE_PAYMENT_REQUIRED';

    public const CALLBACK_EVENT_SHIPPING_ADDRESS = 'SHIPPING_ADDRESS';

    public const KEY_SHIPPING_PREFERENCE = 'shipping_preference';

    public const RETAIN_CONTACT_INFO = 'RETAIN_CONTACT_INFO';

    public const UPDATE_CONTACT_INFO = 'UPDATE_CONTACT_INFO';

    /**
     * @param array<string, mixed> $experienceContext
     *
     * @deprecated the $order argument is unused since Sylius/PayPalPlugin 2.1 and will be removed in Sylius/PayPalPlugin 3.0.
     */
    public function __construct(
        OrderInterface $order,
        private readonly PayPalPurchaseUnit $payPalPurchaseUnit,
        private readonly string $intent,
        private readonly array $experienceContext = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'purchase_units' => [
                $this->payPalPurchaseUnit->toArray(),
            ],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => $this->experienceContext,
                ],
            ],
        ];
    }
}
