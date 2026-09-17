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
use Sylius\PayPalPlugin\Model\ThreeDSecureAuthenticationStatus;
use Sylius\PayPalPlugin\Model\ThreeDSecureEnrollmentStatus;
use Sylius\PayPalPlugin\Model\ThreeDSecureLiabilityShift;

final class ThreeDSecureVerifier implements ThreeDSecureVerifierInterface
{
    public function verify(array $paypalOrderDetails): void
    {
        $authenticationResult = $this->findAuthenticationResult($paypalOrderDetails['payment_source'] ?? null);

        if (null === $authenticationResult) {
            return;
        }

        $threeDSecure = $authenticationResult['three_d_secure'] ?? [];

        match ($this->toEnum(ThreeDSecureEnrollmentStatus::class, $threeDSecure['enrollment_status'] ?? null)) {
            ThreeDSecureEnrollmentStatus::NotReady, ThreeDSecureEnrollmentStatus::Bypassed => null,
            ThreeDSecureEnrollmentStatus::Ready => $this->verifyAuthenticationStatus(
                $this->toEnum(ThreeDSecureAuthenticationStatus::class, $threeDSecure['authentication_status'] ?? null),
            ),
            ThreeDSecureEnrollmentStatus::SystemUnavailable => $this->verifyLiabilityShift(
                $this->toEnum(ThreeDSecureLiabilityShift::class, $authenticationResult['liability_shift'] ?? null),
            ),
            default => throw new ThreeDSecureAuthenticationFailedException(retryable: true),
        };
    }

    private function findAuthenticationResult(mixed $paymentSource): ?array
    {
        if (!is_array($paymentSource)) {
            return null;
        }

        foreach ($paymentSource as $source) {
            if (!is_array($source)) {
                continue;
            }

            $authenticationResult = $source['authentication_result'] ?? $source['card']['authentication_result'] ?? null;
            if (is_array($authenticationResult)) {
                return $authenticationResult;
            }
        }

        return null;
    }

    private function verifyAuthenticationStatus(?ThreeDSecureAuthenticationStatus $authenticationStatus): void
    {
        match ($authenticationStatus) {
            ThreeDSecureAuthenticationStatus::Succeeded, ThreeDSecureAuthenticationStatus::Attempted => null,
            ThreeDSecureAuthenticationStatus::Failed, ThreeDSecureAuthenticationStatus::Refused => throw new ThreeDSecureAuthenticationFailedException(retryable: false),
            ThreeDSecureAuthenticationStatus::Incomplete,
            ThreeDSecureAuthenticationStatus::ChallengeRequired,
            ThreeDSecureAuthenticationStatus::InformationOnly,
            ThreeDSecureAuthenticationStatus::Decoupled => throw new ThreeDSecureAuthenticationFailedException(retryable: true),
            default => throw new ThreeDSecureAuthenticationFailedException(retryable: true),
        };
    }

    private function verifyLiabilityShift(?ThreeDSecureLiabilityShift $liabilityShift): void
    {
        if (ThreeDSecureLiabilityShift::No !== $liabilityShift) {
            throw new ThreeDSecureAuthenticationFailedException(retryable: true);
        }
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|null
     */
    private function toEnum(string $enum, mixed $value): ?\BackedEnum
    {
        return is_string($value) ? $enum::tryFrom($value) : null;
    }
}
