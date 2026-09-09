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
    public const NO_SHIPPING = 'NO_SHIPPING';

    public const PROVIDED_ADDRESS = 'SET_PROVIDED_ADDRESS';

    public const PAYPAL_ADDRESS = 'GET_FROM_FILE';

    public const RETAIN_CONTACT_INFO = 'RETAIN_CONTACT_INFO';

    public const UPDATE_CONTACT_INFO = 'UPDATE_CONTACT_INFO';

    public function __construct(
        private readonly OrderInterface $order,
        private readonly PayPalPurchaseUnit $payPalPurchaseUnit,
        private readonly string $intent,
        private readonly ?string $brandName = null,
        private readonly ?string $localeCode = null,
        private readonly ?string $returnUrl = null,
        private readonly ?string $cancelUrl = null,
    ) {
    }

    public function toArray(): array
    {
        $experienceContext = array_filter(
            [
                'brand_name' => $this->brandName,
                'locale' => $this->localeCode,
                'shipping_preference' => $this->getShippingPreference(),
                'contact_preference' => $this->getContactPreference(),
                'user_action' => 'PAY_NOW',
                'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                'return_url' => $this->returnUrl,
                'cancel_url' => $this->cancelUrl,
                'app_switch_preference' => [
                    'launch_paypal_app' => true,
                ],
            ],
            static fn (mixed $value): bool => null !== $value,
        );

        return [
            'intent' => $this->intent,
            'payment_source' => [
                'paypal' => [
                    'experience_context' => $experienceContext,
                ],
            ],
            'purchase_units' => [
                $this->payPalPurchaseUnit->toArray(),
            ],
        ];
    }

    private function getShippingPreference(): string
    {
        if ($this->order->isShippingRequired()) {
            if (null !== $this->order->getShippingAddress()) {
                return self::PROVIDED_ADDRESS;
            }

            return self::PAYPAL_ADDRESS;
        }

        return self::NO_SHIPPING;
    }

    private function getContactPreference(): string
    {
        return null !== $this->order->getShippingAddress() ? self::RETAIN_CONTACT_INFO : self::UPDATE_CONTACT_INFO;
    }
}
