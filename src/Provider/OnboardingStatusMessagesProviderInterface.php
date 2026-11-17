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

use Sylius\PayPalPlugin\Model\OnboardingStatus;

interface OnboardingStatusMessagesProviderInterface
{
    public const VAULTING_NOT_ACTIVE_MESSAGE = 'sylius_paypal.seller_onboarding_vaulting_not_active';

    /** @return array<string|array{message: string, parameters: array<string, string>}> */
    public function provide(OnboardingStatus $status, bool $sandbox = false): array;
}
