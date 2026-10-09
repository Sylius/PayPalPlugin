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

    public const VERIFICATION_METHOD_SCA_WHEN_REQUIRED = 'SCA_WHEN_REQUIRED';

    public const VERIFICATION_METHOD_SCA_ALWAYS = 'SCA_ALWAYS';

    public const CALLBACK_EVENT_SHIPPING_ADDRESS = 'SHIPPING_ADDRESS';

    public const CALLBACK_EVENT_SHIPPING_OPTIONS = 'SHIPPING_OPTIONS';

    public const KEY_SHIPPING_PREFERENCE = 'shipping_preference';

    public const KEY_ORDER_UPDATE_CALLBACK_CONFIG = 'order_update_callback_config';

    public const RETAIN_CONTACT_INFO = 'RETAIN_CONTACT_INFO';

    public const UPDATE_CONTACT_INFO = 'UPDATE_CONTACT_INFO';

    public const PROCESSING_INSTRUCTION_ORDER_COMPLETE_ON_PAYMENT_APPROVAL = 'ORDER_COMPLETE_ON_PAYMENT_APPROVAL';

    /**
     * @param array<string, mixed>|null $paymentSource
     *
     * @deprecated the $order argument is used only when no $paymentSource is passed since Sylius/PayPalPlugin 2.2 and will be removed in Sylius/PayPalPlugin 3.0.
     */
    public function __construct(
        private readonly OrderInterface $order,
        private readonly PayPalPurchaseUnit $payPalPurchaseUnit,
        private readonly string $intent,
        private readonly ?array $paymentSource = null,
        private readonly ?string $processingInstruction = null,
    ) {
        if (null === $this->paymentSource) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.2',
                'Not passing a $paymentSource to "%s" constructor is deprecated and will be prohibited in 3.0.',
                self::class,
            );
        }
    }

    public function toArray(): array
    {
        $payPalOrder = [
            'intent' => $this->intent,
            'purchase_units' => [
                $this->payPalPurchaseUnit->toArray(),
            ],
        ];

        if (null === $this->paymentSource) {
            $payPalOrder['application_context'] = [self::KEY_SHIPPING_PREFERENCE => $this->getShippingPreference()];
        } elseif ([] !== $this->paymentSource) {
            $payPalOrder['payment_source'] = $this->paymentSource;
        }

        if (null !== $this->processingInstruction) {
            $payPalOrder['processing_instruction'] = $this->processingInstruction;
        }

        return $payPalOrder;
    }

    private function getShippingPreference(): string
    {
        if (!$this->order->isShippingRequired()) {
            return self::NO_SHIPPING;
        }

        return null !== $this->order->getShippingAddress() ? self::PROVIDED_ADDRESS : self::PAYPAL_ADDRESS;
    }
}
