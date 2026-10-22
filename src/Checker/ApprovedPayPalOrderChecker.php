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

namespace Sylius\PayPalPlugin\Checker;

use Sylius\PayPalPlugin\Model\RedirectPaymentSource;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;

final readonly class ApprovedPayPalOrderChecker implements ApprovedPayPalOrderCheckerInterface
{
    private const APPROVED_ORDER_STATUS = 'APPROVED';

    private const CREATED_ORDER_STATUS = 'CREATED';

    private const SOURCES_APPROVED_IN_THE_PAYPAL_WINDOW = [
        PayPalPaymentSourceProviderInterface::PAYPAL,
        PayPalPaymentSourceProviderInterface::VENMO,
    ];

    public function isApproved(array $payPalOrder, string $paymentSource): bool
    {
        if (null !== RedirectPaymentSource::tryFrom($paymentSource)) {
            return false;
        }

        return match ($payPalOrder['status'] ?? null) {
            self::APPROVED_ORDER_STATUS => true,
            self::CREATED_ORDER_STATUS => !in_array($paymentSource, self::SOURCES_APPROVED_IN_THE_PAYPAL_WINDOW, true),
            default => false,
        };
    }
}
