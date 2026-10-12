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

final readonly class OnboardingStatusMessagesProvider implements OnboardingStatusMessagesProviderInterface
{
    public function provide(OnboardingStatus $status): array
    {
        $messages = [];

        if (!$status->arePaymentsReceivable()) {
            $messages[] = 'sylius_paypal.seller_onboarding_payments_not_receivable';
        }

        if (!$status->isPrimaryEmailConfirmed()) {
            $messages[] = 'sylius_paypal.seller_onboarding_primary_email_not_confirmed';
        }

        return $messages;
    }
}
