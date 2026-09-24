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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Checker\PayerActionChecker;
use Sylius\PayPalPlugin\Checker\PayerActionCheckerInterface;

final class PayerActionCheckerTest extends TestCase
{
    private PayerActionChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new PayerActionChecker();
    }

    public function test_it_implements_payer_action_checker_interface(): void
    {
        self::assertInstanceOf(PayerActionCheckerInterface::class, $this->checker);
    }

    public function test_it_recognises_a_payment_the_payer_is_finishing_off_site(): void
    {
        self::assertTrue($this->checker->isAwaitingPayerAction($this->payment(
            PaymentInterface::STATE_PROCESSING,
            ['payment_source' => 'trustly', 'payer_action_url' => 'https://www.paypal.com/payment/trustly?token=X'],
        )));
    }

    public function test_it_leaves_a_wallet_attempt_alone(): void
    {
        self::assertFalse($this->checker->isAwaitingPayerAction($this->payment(
            PaymentInterface::STATE_PROCESSING,
            ['payment_source' => 'paypal'],
        )));
        self::assertFalse($this->checker->isAwaitingPayerAction($this->payment(
            PaymentInterface::STATE_PROCESSING,
            ['payment_source' => 'google_pay'],
        )));
    }

    public function test_it_leaves_a_redirect_attempt_that_never_got_a_link_alone(): void
    {
        self::assertFalse($this->checker->isAwaitingPayerAction($this->payment(
            PaymentInterface::STATE_PROCESSING,
            ['payment_source' => 'trustly'],
        )));
    }

    public function test_it_only_protects_a_payment_that_is_still_processing(): void
    {
        $details = ['payment_source' => 'trustly', 'payer_action_url' => 'https://www.paypal.com/payment/trustly?token=X'];

        self::assertFalse($this->checker->isAwaitingPayerAction($this->payment(PaymentInterface::STATE_COMPLETED, $details)));
        self::assertFalse($this->checker->isAwaitingPayerAction($this->payment(PaymentInterface::STATE_NEW, $details)));
    }

    public function test_it_recognises_the_nonce_it_handed_to_paypal(): void
    {
        $payment = $this->payment(PaymentInterface::STATE_PROCESSING, [
            'payer_action_return_nonce' => 'RETURN_NONCE',
            'payer_action_cancel_nonce' => 'CANCEL_NONCE',
        ]);

        self::assertTrue($this->checker->matchesPayerActionReturnNonce($payment, 'RETURN_NONCE'));
        self::assertTrue($this->checker->matchesPayerActionCancelNonce($payment, 'CANCEL_NONCE'));
        self::assertFalse($this->checker->matchesPayerActionReturnNonce($payment, 'OTHER_NONCE'));
        self::assertFalse($this->checker->matchesPayerActionCancelNonce($payment, ''));
    }

    public function test_it_will_not_cancel_a_payment_with_the_nonce_that_answers_its_return(): void
    {
        $payment = $this->payment(PaymentInterface::STATE_PROCESSING, [
            'payer_action_return_nonce' => 'RETURN_NONCE',
            'payer_action_cancel_nonce' => 'CANCEL_NONCE',
        ]);

        self::assertFalse($this->checker->matchesPayerActionCancelNonce($payment, 'RETURN_NONCE'));
        self::assertFalse($this->checker->matchesPayerActionReturnNonce($payment, 'CANCEL_NONCE'));
    }

    public function test_it_matches_no_nonce_against_a_payment_that_carries_none(): void
    {
        self::assertFalse($this->checker->matchesPayerActionReturnNonce(
            $this->payment(PaymentInterface::STATE_PROCESSING, []),
            '',
        ));
        self::assertFalse($this->checker->matchesPayerActionCancelNonce(
            $this->payment(PaymentInterface::STATE_PROCESSING, ['payer_action_cancel_nonce' => '']),
            '',
        ));
        self::assertFalse($this->checker->matchesPayerActionReturnNonce(
            $this->payment(PaymentInterface::STATE_PROCESSING, ['payer_action_return_nonce' => null]),
            'NONCE',
        ));
    }

    /** @param array<string, mixed> $details */
    private function payment(string $state, array $details): PaymentInterface&MockObject
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getState')->willReturn($state);
        $payment->method('getDetails')->willReturn($details);

        return $payment;
    }
}
