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

    public function test_it_asks_to_retry_an_authentication_result_carrying_no_enrollment_status(): void
    {
        $rejection = $this->rejectionOf(['payment_source' => ['card' => ['authentication_result' => []]]]);

        self::assertTrue($rejection->isRetryable());
    }

    #[DataProvider('acceptedResultProvider')]
    public function test_it_accepts_an_authentication_result(
        string $enrollmentStatus,
        ?string $authenticationStatus,
        string $liabilityShift,
    ): void {
        $this->verifier->verify($this->orderDetails($enrollmentStatus, $authenticationStatus, $liabilityShift));

        $this->expectNotToPerformAssertions();
    }

    #[DataProvider('refusedResultProvider')]
    public function test_it_refuses_an_authentication_result(
        string $enrollmentStatus,
        ?string $authenticationStatus,
        string $liabilityShift,
        bool $retryable,
    ): void {
        $rejection = $this->rejectionOf($this->orderDetails($enrollmentStatus, $authenticationStatus, $liabilityShift));

        self::assertSame($retryable, $rejection->isRetryable());
    }

    public static function acceptedResultProvider(): iterable
    {
        yield 'authenticated, liability shifts' => [
            ThreeDSecureVerifier::ENROLLMENT_READY,
            ThreeDSecureVerifier::AUTHENTICATION_SUCCEEDED,
            ThreeDSecureVerifier::LIABILITY_SHIFT_POSSIBLE,
        ];

        yield 'authentication attempted, liability shifts' => [
            ThreeDSecureVerifier::ENROLLMENT_READY,
            ThreeDSecureVerifier::AUTHENTICATION_ATTEMPTED,
            ThreeDSecureVerifier::LIABILITY_SHIFT_POSSIBLE,
        ];

        yield 'card not enrolled' => [
            ThreeDSecureVerifier::ENROLLMENT_NOT_READY,
            null,
            ThreeDSecureVerifier::LIABILITY_SHIFT_NO,
        ];

        yield 'enrollment system unavailable' => [
            ThreeDSecureVerifier::ENROLLMENT_SYSTEM_UNAVAILABLE,
            null,
            ThreeDSecureVerifier::LIABILITY_SHIFT_NO,
        ];

        yield 'authentication bypassed' => [
            ThreeDSecureVerifier::ENROLLMENT_BYPASSED,
            null,
            ThreeDSecureVerifier::LIABILITY_SHIFT_NO,
        ];
    }

    public static function refusedResultProvider(): iterable
    {
        yield 'authentication failed' => [
            ThreeDSecureVerifier::ENROLLMENT_READY,
            ThreeDSecureVerifier::AUTHENTICATION_FAILED,
            ThreeDSecureVerifier::LIABILITY_SHIFT_NO,
            false,
        ];

        yield 'issuer refused authentication' => [
            ThreeDSecureVerifier::ENROLLMENT_READY,
            ThreeDSecureVerifier::AUTHENTICATION_REFUSED,
            ThreeDSecureVerifier::LIABILITY_SHIFT_NO,
            false,
        ];

        yield 'authentication could not be completed' => [
            ThreeDSecureVerifier::ENROLLMENT_READY,
            ThreeDSecureVerifier::AUTHENTICATION_INCOMPLETE,
            ThreeDSecureVerifier::LIABILITY_SHIFT_UNKNOWN,
            true,
        ];

        yield 'challenge the buyer did not finish' => [
            ThreeDSecureVerifier::ENROLLMENT_READY,
            ThreeDSecureVerifier::AUTHENTICATION_CHALLENGE_REQUIRED,
            ThreeDSecureVerifier::LIABILITY_SHIFT_UNKNOWN,
            true,
        ];

        yield 'authentication status PayPal defines no action for' => [
            ThreeDSecureVerifier::ENROLLMENT_READY,
            'D',
            ThreeDSecureVerifier::LIABILITY_SHIFT_UNKNOWN,
            true,
        ];

        yield 'enrolled card with no authentication status' => [
            ThreeDSecureVerifier::ENROLLMENT_READY,
            null,
            ThreeDSecureVerifier::LIABILITY_SHIFT_UNKNOWN,
            true,
        ];

        yield 'enrollment system unavailable, liability stays with the merchant' => [
            ThreeDSecureVerifier::ENROLLMENT_SYSTEM_UNAVAILABLE,
            null,
            ThreeDSecureVerifier::LIABILITY_SHIFT_UNKNOWN,
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
