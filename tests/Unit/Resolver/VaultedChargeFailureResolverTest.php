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

namespace Tests\Sylius\PayPalPlugin\Unit\Resolver;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Model\VaultedChargeFailure;
use Sylius\PayPalPlugin\Resolver\VaultedChargeFailureResolver;

final class VaultedChargeFailureResolverTest extends TestCase
{
    /** @return iterable<string, array{string, VaultedChargeFailure}> */
    public static function issues(): iterable
    {
        yield 'unknown token' => ['INVALID_VAULT_ID', VaultedChargeFailure::CredentialInvalid];
        yield 'closed payer account' => ['PAYER_ACCOUNT_LOCKED_OR_CLOSED', VaultedChargeFailure::CredentialInvalid];
        yield 'restricted payer account' => ['PAYER_ACCOUNT_RESTRICTED', VaultedChargeFailure::CredentialInvalid];
        yield 'payer cannot pay' => ['PAYER_CANNOT_PAY', VaultedChargeFailure::CredentialInvalid];
        yield 'unusable payment source' => ['PAYMENT_SOURCE_CANNOT_BE_USED', VaultedChargeFailure::CredentialInvalid];
        yield 'payer action required' => ['PAYER_ACTION_REQUIRED', VaultedChargeFailure::PayerActionRequired];
        yield 'alternate funding' => ['REDIRECT_PAYER_FOR_ALTERNATE_FUNDING', VaultedChargeFailure::PayerActionRequired];
        yield 'declined instrument' => ['INSTRUMENT_DECLINED', VaultedChargeFailure::Declined];
        yield 'refused transaction' => ['TRANSACTION_REFUSED', VaultedChargeFailure::Declined];
        yield 'declined by processor' => ['PAYMENT_SOURCE_DECLINED_BY_PROCESSOR', VaultedChargeFailure::Declined];
        yield 'transaction limit' => ['TRANSACTION_LIMIT_EXCEEDED', VaultedChargeFailure::Declined];
        yield 'too many attempts' => ['MAX_NUMBER_OF_PAYMENT_ATTEMPTS_EXCEEDED', VaultedChargeFailure::Declined];
        yield 'merchant restriction' => ['PAYEE_ACCOUNT_RESTRICTED', VaultedChargeFailure::Unknown];
    }

    #[DataProvider('issues')]
    public function test_it_resolves_the_failure_from_the_issue_paypal_reported(string $issue, VaultedChargeFailure $failure): void
    {
        self::assertSame($failure, (new VaultedChargeFailureResolver())->resolveFromIssues([$issue]));
    }

    public function test_it_prefers_an_unusable_token_over_a_decline(): void
    {
        self::assertSame(
            VaultedChargeFailure::CredentialInvalid,
            (new VaultedChargeFailureResolver())->resolveFromIssues(['INSTRUMENT_DECLINED', 'INVALID_VAULT_ID']),
        );
    }

    public function test_it_resolves_an_unknown_failure_without_issues(): void
    {
        self::assertSame(VaultedChargeFailure::Unknown, (new VaultedChargeFailureResolver())->resolveFromIssues([]));
    }

    public function test_it_resolves_an_order_waiting_for_the_payer_as_payer_action_required(): void
    {
        self::assertSame(
            VaultedChargeFailure::PayerActionRequired,
            (new VaultedChargeFailureResolver())->resolveFromPayPalOrder(['id' => 'PAYPAL_ORDER_ID', 'status' => 'PAYER_ACTION_REQUIRED']),
        );
    }

    /** @return iterable<string, array{string, VaultedChargeFailure}> */
    public static function captureStatuses(): iterable
    {
        yield 'declined' => ['DECLINED', VaultedChargeFailure::Declined];
        yield 'failed' => ['FAILED', VaultedChargeFailure::Declined];
        yield 'anything else' => ['REFUNDED', VaultedChargeFailure::Unknown];
    }

    #[DataProvider('captureStatuses')]
    public function test_it_resolves_the_failure_from_the_capture_status(string $status, VaultedChargeFailure $failure): void
    {
        self::assertSame($failure, (new VaultedChargeFailureResolver())->resolveFromPayPalOrder([
            'id' => 'PAYPAL_ORDER_ID',
            'status' => 'COMPLETED',
            'purchase_units' => [['payments' => ['captures' => [['id' => 'CAPTURE_ID', 'status' => $status]]]]],
        ]));
    }

    public function test_it_resolves_an_unknown_failure_for_an_order_without_a_capture(): void
    {
        self::assertSame(VaultedChargeFailure::Unknown, (new VaultedChargeFailureResolver())->resolveFromPayPalOrder(['id' => 'PAYPAL_ORDER_ID', 'status' => 'CREATED']));
    }
}
