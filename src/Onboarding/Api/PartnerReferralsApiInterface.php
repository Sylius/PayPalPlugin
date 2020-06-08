<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Onboarding\Api;

interface PartnerReferralsApiInterface
{
    /**
     * @return string Redirection URL to PayPal
     */
    public function create(string $accessToken): string;
}
