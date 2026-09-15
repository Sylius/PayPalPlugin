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

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Model\PayPalOrder;

final readonly class ExperienceContextProvider implements ExperienceContextProviderInterface
{
    public function provide(
        OrderInterface $order,
        ?string $returnUrl = null,
        ?string $cancelUrl = null,
        ?string $shippingCallbackUrl = null,
    ): array {
        $shippingPreference = $this->getShippingPreference($order);

        $experienceContext = array_filter(
            [
                'locale' => $this->provideLocaleCode($order),
                PayPalOrder::KEY_SHIPPING_PREFERENCE => $shippingPreference,
                'contact_preference' => $this->getContactPreference($order),
                'user_action' => PayPalOrder::USER_ACTION_PAY_NOW,
                'payment_method_preference' => PayPalOrder::PAYMENT_METHOD_PREFERENCE_IMMEDIATE,
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'app_switch_preference' => [
                    'launch_paypal_app' => true,
                ],
            ],
            static fn (mixed $value): bool => null !== $value,
        );

        if (null !== $shippingCallbackUrl && PayPalOrder::PAYPAL_ADDRESS === $shippingPreference) {
            $experienceContext['order_update_callback_config'] = [
                'callback_events' => [PayPalOrder::CALLBACK_EVENT_SHIPPING_ADDRESS],
                'callback_url' => $shippingCallbackUrl,
            ];
        }

        return $experienceContext;
    }

    private function provideLocaleCode(OrderInterface $order): ?string
    {
        $localeCode = $order->getLocaleCode();
        if (null === $localeCode) {
            return null;
        }

        return str_replace('_', '-', $localeCode);
    }

    private function getShippingPreference(OrderInterface $order): string
    {
        if ($order->isShippingRequired()) {
            if (null !== $order->getShippingAddress()) {
                return PayPalOrder::PROVIDED_ADDRESS;
            }

            return PayPalOrder::PAYPAL_ADDRESS;
        }

        return PayPalOrder::NO_SHIPPING;
    }

    private function getContactPreference(OrderInterface $order): string
    {
        return null !== $order->getShippingAddress()
            ? PayPalOrder::RETAIN_CONTACT_INFO
            : PayPalOrder::UPDATE_CONTACT_INFO;
    }
}
