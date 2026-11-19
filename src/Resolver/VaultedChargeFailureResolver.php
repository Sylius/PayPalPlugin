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

namespace Sylius\PayPalPlugin\Resolver;

use Sylius\PayPalPlugin\Model\PayPalCapture;
use Sylius\PayPalPlugin\Model\VaultedChargeFailure;

final readonly class VaultedChargeFailureResolver implements VaultedChargeFailureResolverInterface
{
    private const ORDER_STATUS_PAYER_ACTION_REQUIRED = 'PAYER_ACTION_REQUIRED';

    private const ISSUES = [
        'INVALID_VAULT_ID' => VaultedChargeFailure::CredentialInvalid,
        'PAYER_ACCOUNT_LOCKED_OR_CLOSED' => VaultedChargeFailure::CredentialInvalid,
        'PAYER_ACCOUNT_RESTRICTED' => VaultedChargeFailure::CredentialInvalid,
        'PAYER_CANNOT_PAY' => VaultedChargeFailure::CredentialInvalid,
        'PAYMENT_SOURCE_CANNOT_BE_USED' => VaultedChargeFailure::CredentialInvalid,
        'CARD_EXPIRED' => VaultedChargeFailure::CredentialInvalid,
        'REFERENCED_CARD_EXPIRED' => VaultedChargeFailure::CredentialInvalid,
        'TOKEN_EXPIRED' => VaultedChargeFailure::CredentialInvalid,
        'TOKEN_ID_NOT_FOUND' => VaultedChargeFailure::CredentialInvalid,
        'MISMATCHED_VAULT_ID_TO_PAYMENT_SOURCE' => VaultedChargeFailure::CredentialInvalid,
        'PREVIOUS_TRANSACTION_REFERENCE_HAS_CHARGEBACK' => VaultedChargeFailure::CredentialInvalid,
        'PAYER_ACTION_REQUIRED' => VaultedChargeFailure::PayerActionRequired,
        'REDIRECT_PAYER_FOR_ALTERNATE_FUNDING' => VaultedChargeFailure::PayerActionRequired,
        'INSTRUMENT_DECLINED' => VaultedChargeFailure::Declined,
        'TRANSACTION_REFUSED' => VaultedChargeFailure::Declined,
        'PAYMENT_SOURCE_DECLINED_BY_PROCESSOR' => VaultedChargeFailure::Declined,
        'TRANSACTION_LIMIT_EXCEEDED' => VaultedChargeFailure::Declined,
        'MAX_NUMBER_OF_PAYMENT_ATTEMPTS_EXCEEDED' => VaultedChargeFailure::Declined,
        'TRANSACTION_BLOCKED_BY_PAYEE' => VaultedChargeFailure::Declined,
        'PAYEE_BLOCKED_TRANSACTION' => VaultedChargeFailure::Declined,
    ];

    public function resolveFromIssues(array $issues): VaultedChargeFailure
    {
        foreach (self::ISSUES as $issue => $failure) {
            if (in_array($issue, $issues, true)) {
                return $failure;
            }
        }

        return VaultedChargeFailure::Unknown;
    }

    public function resolveFromPayPalOrder(array $payPalOrder): VaultedChargeFailure
    {
        if (self::ORDER_STATUS_PAYER_ACTION_REQUIRED === ($payPalOrder['status'] ?? null)) {
            return VaultedChargeFailure::PayerActionRequired;
        }

        return match (PayPalCapture::fromPayPalOrder($payPalOrder)?->status()) {
            PayPalCapture::STATUS_DECLINED, PayPalCapture::STATUS_FAILED => VaultedChargeFailure::Declined,
            default => VaultedChargeFailure::Unknown,
        };
    }
}
