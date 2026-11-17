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
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;

interface ThreeDSecureVerifierInterface
{
    /** @throws ThreeDSecureAuthenticationFailedException */
    public function verify(PayPalOrderDetails $payPalOrderDetails): void;
}
