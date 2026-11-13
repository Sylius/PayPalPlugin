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

final readonly class PermissionsUpdateResult
{
    public function __construct(
        private OnboardingStatus $status,
        private PermissionsVerification $verification,
    ) {
    }

    public function status(): OnboardingStatus
    {
        return $this->status;
    }

    public function verification(): PermissionsVerification
    {
        return $this->verification;
    }
}
