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
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;
use Sylius\PayPalPlugin\Model\VaultedChargeFailure;
use Sylius\PayPalPlugin\Provider\PayPalOrderCreatedStatusesProvider;

final readonly class VaultedChargeFailureResolver implements VaultedChargeFailureResolverInterface
{
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

    private const RESPONSE_CODE_ACCOUNT_NOT_FOUND = '0390';

    private const RESPONSE_CODE_INVALID_ACCOUNT = '1330';

    private const RESPONSE_CODE_RESTRICTED_OR_INACTIVE_ACCOUNT = '1352';

    private const RESPONSE_CODE_INVALID_EXPIRATION = '1380';

    private const RESPONSE_CODE_CARD_CLOSED = '5140';

    private const RESPONSE_CODE_PICKUP_CARD = '5150';

    private const RESPONSE_CODE_INVALID_OR_RESTRICTED_CARD = '5180';

    private const RESPONSE_CODE_EXPIRED_CARD = '5400';

    private const RESPONSE_CODE_SCA_REQUIRED = '5650';

    private const RESPONSE_CODE_LOST_OR_STOLEN = '9520';

    private const RESPONSE_CODE_ACCOUNT_CLOSED = 'PP06';

    private const RESPONSE_CODE_EXPIRED_FUNDING_INSTRUMENT = 'PPEF';

    private const RESPONSE_CODE_EXPIRY_DATE = 'PPEX';

    private const RESPONSE_CODE_REATTEMPT_NOT_PERMITTED = 'PPRN';

    private const PAYMENT_ADVICE_CODE_ACCOUNT_CLOSED = '03';

    private const PAYMENT_ADVICE_CODE_STOP_RECURRING = '21';

    public function resolveFromIssues(array $issues): VaultedChargeFailure
    {
        foreach (self::ISSUES as $issue => $failure) {
            if (in_array($issue, $issues, true)) {
                return $failure;
            }
        }

        return VaultedChargeFailure::Unknown;
    }

    public function resolveFromPayPalOrder(PayPalOrderDetails $payPalOrder): VaultedChargeFailure
    {
        if ($payPalOrder->isStatus(PayPalOrderCreatedStatusesProvider::STATUS_PAYER_ACTION_REQUIRED)) {
            return VaultedChargeFailure::PayerActionRequired;
        }

        $capture = $payPalOrder->capture();
        if (null === $capture || !in_array($capture->status(), [PayPalCapture::STATUS_DECLINED, PayPalCapture::STATUS_FAILED], true)) {
            return VaultedChargeFailure::Unknown;
        }

        if (in_array($capture->paymentAdviceCode(), [self::PAYMENT_ADVICE_CODE_ACCOUNT_CLOSED, self::PAYMENT_ADVICE_CODE_STOP_RECURRING], true)) {
            return VaultedChargeFailure::CredentialInvalid;
        }

        return match ($capture->processorResponseCode()) {
            self::RESPONSE_CODE_ACCOUNT_NOT_FOUND,
            self::RESPONSE_CODE_INVALID_ACCOUNT,
            self::RESPONSE_CODE_RESTRICTED_OR_INACTIVE_ACCOUNT,
            self::RESPONSE_CODE_INVALID_EXPIRATION,
            self::RESPONSE_CODE_CARD_CLOSED,
            self::RESPONSE_CODE_PICKUP_CARD,
            self::RESPONSE_CODE_INVALID_OR_RESTRICTED_CARD,
            self::RESPONSE_CODE_EXPIRED_CARD,
            self::RESPONSE_CODE_LOST_OR_STOLEN,
            self::RESPONSE_CODE_ACCOUNT_CLOSED,
            self::RESPONSE_CODE_EXPIRED_FUNDING_INSTRUMENT,
            self::RESPONSE_CODE_EXPIRY_DATE,
            self::RESPONSE_CODE_REATTEMPT_NOT_PERMITTED => VaultedChargeFailure::CredentialInvalid,
            self::RESPONSE_CODE_SCA_REQUIRED => VaultedChargeFailure::PayerActionRequired,
            default => VaultedChargeFailure::Declined,
        };
    }
}
