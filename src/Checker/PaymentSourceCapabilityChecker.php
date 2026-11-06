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

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Provider\MerchantIntegrationStatusProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;

final readonly class PaymentSourceCapabilityChecker implements PaymentSourceCapabilityCheckerInterface
{
    public const REQUIRED_CAPABILITIES = [
        PayPalPaymentSourceProviderInterface::APPLE_PAY => 'APPLE_PAY',
        PayPalPaymentSourceProviderInterface::GOOGLE_PAY => 'GOOGLE_PAY',
    ];

    public function __construct(private MerchantIntegrationStatusProviderInterface $merchantIntegrationStatusProvider)
    {
    }

    public function isAvailable(string $paymentSource, PaymentMethodInterface $paymentMethod): bool
    {
        $capability = self::REQUIRED_CAPABILITIES[$paymentSource] ?? null;
        if (null === $capability) {
            return true;
        }

        $status = $this->merchantIntegrationStatusProvider->provide($paymentMethod);

        return null === $status || $status->hasActiveCapability($capability);
    }
}
