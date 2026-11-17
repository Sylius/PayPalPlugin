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

namespace Sylius\PayPalPlugin\Onboarding\Initiator;

use Sylius\Component\Core\Model\PaymentMethodInterface;

/** @deprecated since Sylius/PayPalPlugin 2.2 and will be removed in Sylius/PayPalPlugin 3.0. */
interface OnboardingInitiatorInterface
{
    /**
     * @return string Redirection URL to PayPal
     */
    public function initiate(PaymentMethodInterface $paymentMethod): string;

    public function supports(PaymentMethodInterface $paymentMethod): bool;
}
