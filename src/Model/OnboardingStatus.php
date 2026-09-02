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

final readonly class OnboardingStatus
{
    public function __construct(
        private bool $paymentsReceivable,
        private bool $primaryEmailConfirmed,
    ) {
    }

    public function arePaymentsReceivable(): bool
    {
        return $this->paymentsReceivable;
    }

    public function isPrimaryEmailConfirmed(): bool
    {
        return $this->primaryEmailConfirmed;
    }

    public function isComplete(): bool
    {
        return $this->paymentsReceivable && $this->primaryEmailConfirmed;
    }
}
