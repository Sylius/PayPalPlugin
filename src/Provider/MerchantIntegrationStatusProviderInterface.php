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

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Exception\MerchantIntegrationStatusUnavailableException;
use Sylius\PayPalPlugin\Model\OnboardingStatus;

interface MerchantIntegrationStatusProviderInterface
{
    /**
     * @return OnboardingStatus|null null when the payment method has no PayPal account yet or PayPal does not know its
     *                               account under the configured partner
     *
     * @throws MerchantIntegrationStatusUnavailableException
     */
    public function provide(PaymentMethodInterface $paymentMethod): ?OnboardingStatus;
}
