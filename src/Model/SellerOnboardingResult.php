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

namespace Sylius\PayPalPlugin\Model;

final readonly class SellerOnboardingResult
{
    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $merchantId,
        private OnboardingStatus $status,
    ) {
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function getMerchantId(): string
    {
        return $this->merchantId;
    }

    public function getStatus(): OnboardingStatus
    {
        return $this->status;
    }
}
