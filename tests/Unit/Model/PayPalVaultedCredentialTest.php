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

namespace Tests\Sylius\PayPalPlugin\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Model\PayPalVaultedCredential;

final class PayPalVaultedCredentialTest extends TestCase
{
    public function test_it_reads_the_vaulted_card_and_the_transaction_that_first_charged_it_from_the_first_payment_details(): void
    {
        $credential = PayPalVaultedCredential::fromPaymentDetails(PayPalPaymentDetails::fromArray([
            'vault_id' => 'CARD_VAULT_ID',
            'payment_source' => 'card',
            'transaction_id' => 'FIRST_CAPTURE_ID',
        ]));

        self::assertEquals(new PayPalVaultedCredential('CARD_VAULT_ID', 'card', 'FIRST_CAPTURE_ID'), $credential);
        self::assertTrue($credential->isCard());
    }

    public function test_it_reads_the_authorization_as_the_previous_transaction_of_a_card_paypal_has_not_captured(): void
    {
        self::assertSame('AUTHORIZATION_ID', PayPalVaultedCredential::fromPaymentDetails(PayPalPaymentDetails::fromArray([
            'vault_id' => 'CARD_VAULT_ID',
            'payment_source' => 'card',
            'authorization_id' => 'AUTHORIZATION_ID',
        ]))?->previousTransactionReference());
        self::assertSame('CAPTURE_ID', PayPalVaultedCredential::fromPaymentDetails(PayPalPaymentDetails::fromArray([
            'vault_id' => 'CARD_VAULT_ID',
            'payment_source' => 'card',
            'authorization_id' => 'AUTHORIZATION_ID',
            'transaction_id' => 'CAPTURE_ID',
        ]))?->previousTransactionReference());
    }

    public function test_it_reads_a_vaulted_wallet_from_payment_details_that_name_no_payment_source(): void
    {
        self::assertEquals(
            new PayPalVaultedCredential('VAULT_ID'),
            PayPalVaultedCredential::fromPaymentDetails(PayPalPaymentDetails::fromArray(['vault_id' => 'VAULT_ID'])),
        );
    }

    public function test_it_reads_no_credential_from_payment_details_without_a_vault(): void
    {
        self::assertNull(PayPalVaultedCredential::fromPaymentDetails(PayPalPaymentDetails::fromArray(['payment_source' => 'card', 'transaction_id' => 'FIRST_CAPTURE_ID'])));
    }

    public function test_it_round_trips_through_an_array(): void
    {
        $credential = new PayPalVaultedCredential('CARD_VAULT_ID', 'card', 'FIRST_CAPTURE_ID');

        self::assertSame(
            ['vault_id' => 'CARD_VAULT_ID', 'payment_source' => 'card', 'previous_transaction_reference' => 'FIRST_CAPTURE_ID'],
            $credential->toArray(),
        );
        self::assertEquals($credential, PayPalVaultedCredential::fromArray($credential->toArray()));
    }

    public function test_it_leaves_out_an_unknown_previous_transaction(): void
    {
        self::assertSame(['vault_id' => 'VAULT_ID', 'payment_source' => 'paypal'], (new PayPalVaultedCredential('VAULT_ID'))->toArray());
        self::assertNull(PayPalVaultedCredential::fromArray(['vault_id' => 'VAULT_ID', 'previous_transaction_reference' => ''])?->previousTransactionReference());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function provideArraysWithoutUsableCredential(): iterable
    {
        yield 'no vault id' => [[]];
        yield 'empty vault id' => [['vault_id' => '']];
        yield 'vault id that is not a string' => [['vault_id' => 123]];
        yield 'payment source that cannot be vaulted' => [['vault_id' => 'VAULT_ID', 'payment_source' => 'venmo']];
    }

    /** @param array<string, mixed> $data */
    #[DataProvider('provideArraysWithoutUsableCredential')]
    public function test_it_reads_no_credential_from_an_array_that_does_not_describe_one(array $data): void
    {
        self::assertNull(PayPalVaultedCredential::fromArray($data));
    }

    public function test_it_refuses_a_payment_source_that_cannot_be_vaulted(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PayPalVaultedCredential('VAULT_ID', 'venmo');
    }
}
