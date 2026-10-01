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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Exception\ThreeDSecureAuthenticationFailedException;
use Sylius\PayPalPlugin\Model\ThreeDSecureAuthenticationStatus;
use Sylius\PayPalPlugin\Model\ThreeDSecureEnrollmentStatus;
use Sylius\PayPalPlugin\Model\ThreeDSecureLiabilityShift;
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

    public function test_it_reads_an_authentication_result_nested_under_a_wallet(): void
    {
        $rejection = $this->rejectionOf(['payment_source' => ['google_pay' => ['card' => [
            'authentication_result' => [
                'liability_shift' => ThreeDSecureLiabilityShift::No->value,
                'three_d_secure' => [
                    'enrollment_status' => ThreeDSecureEnrollmentStatus::Ready->value,
                    'authentication_status' => ThreeDSecureAuthenticationStatus::Failed->value,
                ],
            ],
        ]]]]);

        self::assertFalse($rejection->isRetryable());
    }

    public function test_it_accepts_a_wallet_payment_source_carrying_no_authentication_result(): void
    {
        $this->verifier->verify(['payment_source' => ['google_pay' => ['card' => ['last_digits' => '1111']]]]);

        $this->expectNotToPerformAssertions();
    }

    public function test_it_asks_to_retry_an_authentication_result_carrying_no_enrollment_status(): void
    {
        $rejection = $this->rejectionOf(['payment_source' => ['card' => ['authentication_result' => []]]]);

        self::assertTrue($rejection->isRetryable());
    }

    #[DataProvider('acceptedResultProvider')]
    public function test_it_accepts_an_authentication_result(
        ThreeDSecureEnrollmentStatus $enrollmentStatus,
        ThreeDSecureAuthenticationStatus|string|null $authenticationStatus,
        ThreeDSecureLiabilityShift $liabilityShift,
    ): void {
        $this->verifier->verify($this->orderDetails($enrollmentStatus, $authenticationStatus, $liabilityShift));

        $this->expectNotToPerformAssertions();
    }

    #[DataProvider('refusedResultProvider')]
    public function test_it_refuses_an_authentication_result(
        ThreeDSecureEnrollmentStatus $enrollmentStatus,
        ThreeDSecureAuthenticationStatus|string|null $authenticationStatus,
        ThreeDSecureLiabilityShift $liabilityShift,
        bool $retryable,
    ): void {
        $rejection = $this->rejectionOf($this->orderDetails($enrollmentStatus, $authenticationStatus, $liabilityShift));

        self::assertSame($retryable, $rejection->isRetryable());
    }

    public static function acceptedResultProvider(): iterable
    {
        yield 'authenticated, liability shifts' => [
            ThreeDSecureEnrollmentStatus::Ready,
            ThreeDSecureAuthenticationStatus::Succeeded,
            ThreeDSecureLiabilityShift::Possible,
        ];

        yield 'authentication attempted, liability shifts' => [
            ThreeDSecureEnrollmentStatus::Ready,
            ThreeDSecureAuthenticationStatus::Attempted,
            ThreeDSecureLiabilityShift::Possible,
        ];

        yield 'card not enrolled' => [
            ThreeDSecureEnrollmentStatus::NotReady,
            null,
            ThreeDSecureLiabilityShift::No,
        ];

        yield 'enrollment system unavailable' => [
            ThreeDSecureEnrollmentStatus::SystemUnavailable,
            null,
            ThreeDSecureLiabilityShift::No,
        ];

        yield 'authentication bypassed' => [
            ThreeDSecureEnrollmentStatus::Bypassed,
            null,
            ThreeDSecureLiabilityShift::No,
        ];
    }

    public static function refusedResultProvider(): iterable
    {
        yield 'authentication failed' => [
            ThreeDSecureEnrollmentStatus::Ready,
            ThreeDSecureAuthenticationStatus::Failed,
            ThreeDSecureLiabilityShift::No,
            false,
        ];

        yield 'issuer refused authentication' => [
            ThreeDSecureEnrollmentStatus::Ready,
            ThreeDSecureAuthenticationStatus::Refused,
            ThreeDSecureLiabilityShift::No,
            false,
        ];

        yield 'authentication could not be completed' => [
            ThreeDSecureEnrollmentStatus::Ready,
            ThreeDSecureAuthenticationStatus::Incomplete,
            ThreeDSecureLiabilityShift::Unknown,
            true,
        ];

        yield 'challenge the buyer did not finish' => [
            ThreeDSecureEnrollmentStatus::Ready,
            ThreeDSecureAuthenticationStatus::ChallengeRequired,
            ThreeDSecureLiabilityShift::Unknown,
            true,
        ];

        yield 'information only authentication' => [
            ThreeDSecureEnrollmentStatus::Ready,
            ThreeDSecureAuthenticationStatus::InformationOnly,
            ThreeDSecureLiabilityShift::Unknown,
            true,
        ];

        yield 'decoupled authentication' => [
            ThreeDSecureEnrollmentStatus::Ready,
            ThreeDSecureAuthenticationStatus::Decoupled,
            ThreeDSecureLiabilityShift::Unknown,
            true,
        ];

        yield 'authentication status PayPal has not defined yet' => [
            ThreeDSecureEnrollmentStatus::Ready,
            'X',
            ThreeDSecureLiabilityShift::Unknown,
            true,
        ];

        yield 'enrolled card with no authentication status' => [
            ThreeDSecureEnrollmentStatus::Ready,
            null,
            ThreeDSecureLiabilityShift::Unknown,
            true,
        ];

        yield 'enrollment system unavailable, liability stays with the merchant' => [
            ThreeDSecureEnrollmentStatus::SystemUnavailable,
            null,
            ThreeDSecureLiabilityShift::Unknown,
            true,
        ];
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

    private function orderDetails(
        ThreeDSecureEnrollmentStatus $enrollmentStatus,
        ThreeDSecureAuthenticationStatus|string|null $authenticationStatus,
        ThreeDSecureLiabilityShift $liabilityShift,
    ): array {
        $threeDSecure = ['enrollment_status' => $enrollmentStatus->value];
        if (null !== $authenticationStatus) {
            $threeDSecure['authentication_status'] = $authenticationStatus instanceof ThreeDSecureAuthenticationStatus
                ? $authenticationStatus->value
                : $authenticationStatus;
        }

        return [
            'payment_source' => [
                'card' => [
                    'authentication_result' => [
                        'liability_shift' => $liabilityShift->value,
                        'three_d_secure' => $threeDSecure,
                    ],
                ],
            ],
        ];
    }
}
