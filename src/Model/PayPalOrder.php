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

    public const CALLBACK_EVENT_SHIPPING_ADDRESS = 'SHIPPING_ADDRESS';

    /** @var string */
    private $intent;

    /** @var PayPalPurchaseUnit */
    private $payPalPurchaseUnit;

    /** @var OrderInterface */
    private $order;

    public function __construct(
        OrderInterface $order,
        PayPalPurchaseUnit $payPalPurchaseUnit,
        string $intent,
        private readonly ?string $returnUrl = null,
        private readonly ?string $cancelUrl = null,
        private readonly ?string $shippingCallbackUrl = null,
    ) {
        $this->payPalPurchaseUnit = $payPalPurchaseUnit;
        $this->order = $order;
        $this->intent = $intent;
    }

    public function toArray(): array
    {
        $shippingPreference = $this->getShippingPreference();

        $payPalOrder = [
            'intent' => $this->intent,
            'purchase_units' => [
                $this->payPalPurchaseUnit->toArray(),
            ],
        ];

        // PayPal rejects an order carrying shipping_preference or user_action in both places with
        // INCOMPATIBLE_PARAMETER_VALUE, so the two context blocks are mutually exclusive.
        if (self::PAYPAL_ADDRESS === $shippingPreference) {
            $payPalOrder['payment_source'] = [
                'paypal' => [
                    'experience_context' => $this->getExperienceContext($shippingPreference),
                ],
            ];

            return $payPalOrder;
        }

        $payPalOrder['application_context'] = [
            'shipping_preference' => $shippingPreference,
            'user_action' => self::USER_ACTION_PAY_NOW,
        ];

        return $payPalOrder;
    }

    private function getExperienceContext(string $shippingPreference): array
    {
        $experienceContext = [
            'shipping_preference' => $shippingPreference,
            'user_action' => self::USER_ACTION_PAY_NOW,
        ];

        if (null !== $this->returnUrl) {
            $experienceContext['return_url'] = $this->returnUrl;
        }

        if (null !== $this->cancelUrl) {
            $experienceContext['cancel_url'] = $this->cancelUrl;
        }

        if (null !== $this->shippingCallbackUrl) {
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
            if ($this->order->getShippingAddress() !== null) {
                return self::PROVIDED_ADDRESS;
            }

            return self::PAYPAL_ADDRESS;
        }

        return self::NO_SHIPPING;
    }
}
