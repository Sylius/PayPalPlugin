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

namespace Sylius\PayPalPlugin\Verifier;

use Sylius\PayPalPlugin\Exception\ThreeDSecureAuthenticationFailedException;

final class ThreeDSecureVerifier implements ThreeDSecureVerifierInterface
{
    public const ENROLLMENT_READY = 'Y';

    public const ENROLLMENT_NOT_READY = 'N';

    public const ENROLLMENT_SYSTEM_UNAVAILABLE = 'U';

    public const ENROLLMENT_BYPASSED = 'B';

    public const AUTHENTICATION_SUCCEEDED = 'Y';

    public const AUTHENTICATION_ATTEMPTED = 'A';

    public const AUTHENTICATION_FAILED = 'N';

    public const AUTHENTICATION_REFUSED = 'R';

    public const AUTHENTICATION_INCOMPLETE = 'U';

    public const AUTHENTICATION_CHALLENGE_REQUIRED = 'C';

    public const LIABILITY_SHIFT_NO = 'NO';

    public const LIABILITY_SHIFT_POSSIBLE = 'POSSIBLE';

    public const LIABILITY_SHIFT_UNKNOWN = 'UNKNOWN';

    public function verify(array $paypalOrderDetails): void
    {
        $authenticationResult = $paypalOrderDetails['payment_source']['card']['authentication_result'] ?? null;

        if (!is_array($authenticationResult)) {
            return;
        }

        $threeDSecure = $authenticationResult['three_d_secure'] ?? [];

        match ($threeDSecure['enrollment_status'] ?? null) {
            self::ENROLLMENT_NOT_READY, self::ENROLLMENT_BYPASSED => null,
            self::ENROLLMENT_READY => $this->verifyAuthenticationStatus($threeDSecure['authentication_status'] ?? null),
            self::ENROLLMENT_SYSTEM_UNAVAILABLE => $this->verifyLiabilityShift($authenticationResult['liability_shift'] ?? null),
            default => throw new ThreeDSecureAuthenticationFailedException(retryable: true),
        };
    }

    private function verifyAuthenticationStatus(mixed $authenticationStatus): void
    {
        match ($authenticationStatus) {
            self::AUTHENTICATION_SUCCEEDED, self::AUTHENTICATION_ATTEMPTED => null,
            self::AUTHENTICATION_FAILED, self::AUTHENTICATION_REFUSED => throw new ThreeDSecureAuthenticationFailedException(retryable: false),
            self::AUTHENTICATION_INCOMPLETE, self::AUTHENTICATION_CHALLENGE_REQUIRED => throw new ThreeDSecureAuthenticationFailedException(retryable: true),
            default => throw new ThreeDSecureAuthenticationFailedException(retryable: true),
        };
    }

    private function verifyLiabilityShift(mixed $liabilityShift): void
    {
        if (self::LIABILITY_SHIFT_NO !== $liabilityShift) {
            throw new ThreeDSecureAuthenticationFailedException(retryable: true);
        }
    }
}
