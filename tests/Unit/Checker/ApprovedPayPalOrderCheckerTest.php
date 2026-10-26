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

namespace Tests\Sylius\PayPalPlugin\Unit\Checker;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Checker\ApprovedPayPalOrderChecker;
use Sylius\PayPalPlugin\Checker\ApprovedPayPalOrderCheckerInterface;
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;

final class ApprovedPayPalOrderCheckerTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function approvedOrders(): iterable
    {
        yield 'approved in the PayPal window' => ['paypal', 'APPROVED'];
        yield 'approved in the Venmo window' => ['venmo', 'APPROVED'];
        yield 'approved card' => ['card', 'APPROVED'];
        yield 'card left created after 3D Secure' => ['card', 'CREATED'];
        yield 'Google Pay left created after the wallet' => ['google_pay', 'CREATED'];
    }

    /** @return iterable<string, array{string, string}> */
    public static function ordersThePayerHasNotApproved(): iterable
    {
        yield 'created, left in the PayPal window' => ['paypal', 'CREATED'];
        yield 'created, left in the Venmo window' => ['venmo', 'CREATED'];
        yield 'payer action required' => ['card', 'PAYER_ACTION_REQUIRED'];
        yield 'voided' => ['card', 'VOIDED'];
        yield 'already completed' => ['paypal', 'COMPLETED'];
        yield 'paid at the bank' => ['trustly', 'APPROVED'];
    }

    public function test_it_implements_approved_paypal_order_checker_interface(): void
    {
        self::assertInstanceOf(ApprovedPayPalOrderCheckerInterface::class, new ApprovedPayPalOrderChecker());
    }

    #[DataProvider('approvedOrders')]
    public function test_it_knows_an_order_the_payer_has_approved(string $paymentSource, string $status): void
    {
        self::assertTrue((new ApprovedPayPalOrderChecker())->isApproved(PayPalOrderDetails::fromArray(['status' => $status]), $paymentSource));
    }

    #[DataProvider('ordersThePayerHasNotApproved')]
    public function test_it_knows_an_order_that_is_not_waiting_to_be_completed(string $paymentSource, string $status): void
    {
        self::assertFalse((new ApprovedPayPalOrderChecker())->isApproved(PayPalOrderDetails::fromArray(['status' => $status]), $paymentSource));
    }

    public function test_it_knows_no_approval_of_an_order_without_a_status(): void
    {
        self::assertFalse((new ApprovedPayPalOrderChecker())->isApproved(PayPalOrderDetails::fromArray([]), 'paypal'));
    }
}
