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

namespace Tests\Sylius\PayPalPlugin\Unit\Verifier;

use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Exception\ThreeDSecureAuthenticationFailedException;
use Sylius\PayPalPlugin\Verifier\ThreeDSecureVerifier;
use Sylius\PayPalPlugin\Verifier\ThreeDSecureVerifierInterface;

final class ThreeDSecureVerifierTest extends TestCase
{
    private ThreeDSecureVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->verifier = new ThreeDSecureVerifier();
    }

    public function test_it_implements_three_d_secure_verifier_interface(): void
    {
        self::assertInstanceOf(ThreeDSecureVerifierInterface::class, $this->verifier);
    }

    public function test_it_accepts_an_order_carrying_no_authentication_result(): void
    {
        $this->verifier->verify(['id' => 'PAYPAL_ORDER_ID', 'status' => 'APPROVED']);

        $this->expectNotToPerformAssertions();
    }

    public function test_it_accepts_an_order_whose_payment_source_is_not_a_card(): void
    {
        $this->verifier->verify(['payment_source' => ['paypal' => ['email_address' => 'buyer@example.com']]]);

        $this->expectNotToPerformAssertions();
    }

    public function test_it_accepts_a_successful_authentication(): void
    {
        $this->verifier->verify($this->orderDetails(
            ThreeDSecureVerifier::ENROLLMENT_READY,
            ThreeDSecureVerifier::AUTHENTICATION_SUCCEEDED,
            ThreeDSecureVerifier::LIABILITY_SHIFT_POSSIBLE,
        ));

        $this->expectNotToPerformAssertions();
    }

    public function test_it_accepts_an_attempted_authentication(): void
    {
        $this->verifier->verify($this->orderDetails(
            ThreeDSecureVerifier::ENROLLMENT_READY,
            ThreeDSecureVerifier::AUTHENTICATION_ATTEMPTED,
            ThreeDSecureVerifier::LIABILITY_SHIFT_POSSIBLE,
        ));

        $this->expectNotToPerformAssertions();
    }

    public function test_it_rejects_a_failed_authentication(): void
    {
        $rejection = $this->rejectionOf($this->orderDetails(
            ThreeDSecureVerifier::ENROLLMENT_READY,
            ThreeDSecureVerifier::AUTHENTICATION_FAILED,
            ThreeDSecureVerifier::LIABILITY_SHIFT_NO,
        ));

        self::assertFalse($rejection->isRetryable());
    }

    public function test_it_rejects_an_authentication_the_issuer_refused(): void
    {
        $rejection = $this->rejectionOf($this->orderDetails(
            ThreeDSecureVerifier::ENROLLMENT_READY,
            ThreeDSecureVerifier::AUTHENTICATION_REFUSED,
            ThreeDSecureVerifier::LIABILITY_SHIFT_NO,
        ));

        self::assertFalse($rejection->isRetryable());
    }

    public function test_it_asks_to_retry_an_authentication_that_could_not_be_completed(): void
    {
        $rejection = $this->rejectionOf($this->orderDetails(
            ThreeDSecureVerifier::ENROLLMENT_READY,
            'U',
            ThreeDSecureVerifier::LIABILITY_SHIFT_UNKNOWN,
        ));

        self::assertTrue($rejection->isRetryable());
    }

    public function test_it_asks_to_retry_a_challenge_the_buyer_did_not_finish(): void
    {
        $rejection = $this->rejectionOf($this->orderDetails(
            ThreeDSecureVerifier::ENROLLMENT_READY,
            'C',
            ThreeDSecureVerifier::LIABILITY_SHIFT_UNKNOWN,
        ));

        self::assertTrue($rejection->isRetryable());
    }

    public function test_it_asks_to_retry_an_unrecognised_authentication_status(): void
    {
        $rejection = $this->rejectionOf($this->orderDetails(
            ThreeDSecureVerifier::ENROLLMENT_READY,
            'I',
            ThreeDSecureVerifier::LIABILITY_SHIFT_UNKNOWN,
        ));

        self::assertTrue($rejection->isRetryable());
    }

    public function test_it_accepts_a_card_that_is_not_enrolled(): void
    {
        $this->verifier->verify($this->orderDetails(
            ThreeDSecureVerifier::ENROLLMENT_NOT_READY,
            null,
            ThreeDSecureVerifier::LIABILITY_SHIFT_NO,
        ));

        $this->expectNotToPerformAssertions();
    }

    public function test_it_accepts_an_unavailable_authentication_system(): void
    {
        $this->verifier->verify($this->orderDetails(
            ThreeDSecureVerifier::ENROLLMENT_SYSTEM_UNAVAILABLE,
            null,
            ThreeDSecureVerifier::LIABILITY_SHIFT_NO,
        ));

        $this->expectNotToPerformAssertions();
    }

    public function test_it_asks_to_retry_an_unavailable_authentication_system_without_liability_shift(): void
    {
        $rejection = $this->rejectionOf($this->orderDetails(
            ThreeDSecureVerifier::ENROLLMENT_SYSTEM_UNAVAILABLE,
            null,
            ThreeDSecureVerifier::LIABILITY_SHIFT_UNKNOWN,
        ));

        self::assertTrue($rejection->isRetryable());
    }

    public function test_it_accepts_a_bypassed_authentication(): void
    {
        $this->verifier->verify($this->orderDetails(
            ThreeDSecureVerifier::ENROLLMENT_BYPASSED,
            null,
            ThreeDSecureVerifier::LIABILITY_SHIFT_NO,
        ));

        $this->expectNotToPerformAssertions();
    }

    public function test_it_asks_to_retry_an_authentication_result_carrying_no_enrollment_status(): void
    {
        self::assertTrue($this->rejectionOf(['payment_source' => ['card' => ['authentication_result' => []]]])->isRetryable());
    }

    private function rejectionOf(array $paypalOrderDetails): ThreeDSecureAuthenticationFailedException
    {
        try {
            $this->verifier->verify($paypalOrderDetails);
        } catch (ThreeDSecureAuthenticationFailedException $exception) {
            return $exception;
        }

        self::fail('Expected the verifier to reject the authentication result.');
    }

    private function orderDetails(string $enrollmentStatus, ?string $authenticationStatus, string $liabilityShift): array
    {
        $threeDSecure = ['enrollment_status' => $enrollmentStatus];
        if (null !== $authenticationStatus) {
            $threeDSecure['authentication_status'] = $authenticationStatus;
        }

        return [
            'payment_source' => [
                'card' => [
                    'authentication_result' => [
                        'liability_shift' => $liabilityShift,
                        'three_d_secure' => $threeDSecure,
                    ],
                ],
            ],
        ];
    }
}
