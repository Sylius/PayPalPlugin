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

namespace Sylius\PayPalPlugin\Exception;

use Sylius\PayPalPlugin\Model\OnboardingStatus;

final class PaymentMethodCouldNotBeEnabledException extends \Exception
{
    public function __construct(private readonly ?OnboardingStatus $onboardingStatus = null)
    {
        parent::__construct('PayPal payment method could not be enabled');
    }

    public function getOnboardingStatus(): ?OnboardingStatus
    {
        return $this->onboardingStatus;
    }
}
