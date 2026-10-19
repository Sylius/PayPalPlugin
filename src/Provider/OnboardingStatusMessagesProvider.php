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
    public function __construct(private HostProviderInterface $hostProvider)
    {
    }

    public function provide(OnboardingStatus $status, bool $sandbox = false): array
    {
        $messages = [];

        if (!$status->arePaymentsReceivable()) {
            $messages[] = [
                'message' => 'sylius_paypal.seller_onboarding_payments_not_receivable',
                'parameters' => [
                    '%paypal_url%' => $this->hostProvider->getWebUrlForMode($sandbox),
                ],
            ];
        }

        if (!$status->isPrimaryEmailConfirmed()) {
            $messages[] = [
                'message' => 'sylius_paypal.seller_onboarding_primary_email_not_confirmed',
                'parameters' => [
                    '%business_profile_url%' => $this->hostProvider->getWebUrlForMode($sandbox) . '/businessprofile/settings',
                ],
            ];
        }

        if (!$status->isVaultingEnabled()) {
            $messages[] = [
                'message' => 'sylius_paypal.seller_onboarding_vaulting_not_active',
                'parameters' => [
                    '%status%' => $status->getVaultingCapabilityStatus() ?? 'NOT_REQUESTED',
                ],
            ];
        }

        return $messages;
    }
}
