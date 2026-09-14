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
        // brand_name is intentionally omitted: PayPal then falls back to the business name registered on
        // the merchant's account. Decorate this provider to send a custom one.
        $experienceContext = array_filter(
            [
                'locale' => $this->provideLocaleCode($order),
                PayPalOrder::KEY_SHIPPING_PREFERENCE => $this->getShippingPreference($order),
                'contact_preference' => $this->getContactPreference($order),
                'user_action' => PayPalOrder::USER_ACTION_PAY_NOW,
                'payment_method_preference' => PayPalOrder::PAYMENT_METHOD_PREFERENCE_IMMEDIATE,
                // return_url and cancel_url must be identical for app switch to function.
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'app_switch_preference' => [
                    'launch_paypal_app' => true,
                ],
            ],
            static fn (mixed $value): bool => null !== $value,
        );

        if (null !== $shippingCallbackUrl) {
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

        // PayPal expects a BCP 47 locale (e.g. "en-US"), while Sylius stores it as "en_US".
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
