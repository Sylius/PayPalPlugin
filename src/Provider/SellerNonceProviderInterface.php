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

namespace Sylius\PayPalPlugin\Provider;

interface SellerNonceProviderInterface
{
    /**
     * Generates a random seller nonce (at least 40 characters), stores it under
     * the given key and returns it. Used as the code_verifier during onboarding.
     */
    public function generateFor(string $sellerKey): string;

    /**
     * Returns and removes the stored nonce (single use). Null when missing or expired.
     */
    public function consume(string $sellerKey): ?string;
}
