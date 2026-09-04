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
        private readonly ?string $shippingCallbackUrl = null,
    ) {
        $this->payPalPurchaseUnit = $payPalPurchaseUnit;
        $this->order = $order;
        $this->intent = $intent;
    }

    public function toArray(): array
    {
        $shippingPreference = $this->getShippingPreference();

        $applicationContext = [
            'shipping_preference' => $shippingPreference,
        ];

        // Only the "shortcut" (cart/product page) placements have no shipping address yet at
        // create-order time, which is exactly when PayPal needs a live shipping-options callback -
        // the checkout payment step already has one, so it never sets $shippingCallbackUrl.
        //
        // KNOWN GAP, confirmed live (2026-09-04): nesting this under the documented
        // payment_source.paypal.experience_context path instead makes PayPal treat the order as an
        // explicitly-selected payment source, returning status=PAYER_ACTION_REQUIRED with a redirect
        // "payer-action" link instead of the SDK-managed flow this app's Payum action expects - it
        // broke order creation entirely (confirmed via a live sandbox request/response capture).
        // Keeping it under application_context here instead: unconfirmed whether PayPal actually reads
        // it from this legacy location, but it does not break the working flow. Needs real
        // investigation (or a PayPal support answer) into how to get a working callback without
        // switching the order into a payer-action/redirect flow, before this is revisited.
        if (self::PAYPAL_ADDRESS === $shippingPreference && null !== $this->shippingCallbackUrl) {
            $applicationContext['order_update_callback_config'] = [
                'callback_events' => ['SHIPPING_ADDRESS'],
                'callback_url' => $this->shippingCallbackUrl,
            ];
        }

        return [
            'intent' => $this->intent,
            'purchase_units' => [
                $this->payPalPurchaseUnit->toArray(),
            ],
            'application_context' => $applicationContext,
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
