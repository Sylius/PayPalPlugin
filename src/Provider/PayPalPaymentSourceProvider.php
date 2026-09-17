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
use Sylius\PayPalPlugin\Exception\UnsupportedPayPalPaymentSourceException;
use Sylius\PayPalPlugin\Model\PayPalOrder;

final class PayPalPaymentSourceProvider implements PayPalPaymentSourceProviderInterface
{
    public function provide(OrderInterface $order, string $paymentSource, array $experienceContext): array
    {
        return match ($paymentSource) {
            self::PAYPAL => [self::PAYPAL => ['experience_context' => $experienceContext]],
            self::GOOGLE_PAY => [self::GOOGLE_PAY => [
                'attributes' => ['verification' => ['method' => PayPalOrder::VERIFICATION_METHOD_SCA_WHEN_REQUIRED]],
            ]],
            default => throw new UnsupportedPayPalPaymentSourceException($paymentSource),
        };
    }

    public function supports(string $paymentSource): bool
    {
        return in_array($paymentSource, [self::PAYPAL, self::GOOGLE_PAY], true);
    }
}
