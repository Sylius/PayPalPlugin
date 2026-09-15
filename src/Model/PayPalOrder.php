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
     */
    public function __construct(
        private readonly OrderInterface $order,
        private readonly PayPalPurchaseUnit $payPalPurchaseUnit,
        private readonly string $intent,
        private readonly ?string $returnUrl = null,
        private readonly ?string $cancelUrl = null,
        private readonly ?string $shippingCallbackUrl = null,
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
                    'experience_context' => [] === $this->experienceContext
                        ? $this->getExperienceContext($this->getShippingPreference())
                        : $this->experienceContext,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getExperienceContext(string $shippingPreference): array
    {
        $experienceContext = [
            self::KEY_SHIPPING_PREFERENCE => $shippingPreference,
            'user_action' => self::USER_ACTION_PAY_NOW,
        ];

        if (null !== $this->returnUrl) {
            $experienceContext['return_url'] = $this->returnUrl;
        }

        if (null !== $this->cancelUrl) {
            $experienceContext['cancel_url'] = $this->cancelUrl;
        }

        if (null !== $this->shippingCallbackUrl && self::PAYPAL_ADDRESS === $shippingPreference) {
            $experienceContext['order_update_callback_config'] = [
                'callback_events' => [self::CALLBACK_EVENT_SHIPPING_ADDRESS],
                'callback_url' => $this->shippingCallbackUrl,
            ];
        }

        return $experienceContext;
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
}
