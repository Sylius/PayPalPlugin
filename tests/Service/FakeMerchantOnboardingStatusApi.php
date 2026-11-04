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

namespace Tests\Sylius\PayPalPlugin\Service;

use Sylius\PayPalPlugin\Api\MerchantOnboardingStatusApiInterface;
use Sylius\PayPalPlugin\Model\OnboardingStatus;

final class FakeMerchantOnboardingStatusApi implements MerchantOnboardingStatusApiInterface
{
    public const ACTIVE_CAPABILITIES = ['APPLE_PAY', 'GOOGLE_PAY'];

    /** @var list<string> */
    public static array $activeCapabilities = self::ACTIVE_CAPABILITIES;

    public function get(string $sellerToken, string $partnerId, string $merchantId, bool $sandbox): OnboardingStatus
    {
        return new OnboardingStatus(true, true, self::$activeCapabilities);
    }
}
