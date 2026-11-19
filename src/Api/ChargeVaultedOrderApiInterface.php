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

namespace Sylius\PayPalPlugin\Api;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Model\PayPalVaultedCredential;

interface ChargeVaultedOrderApiInterface
{
    /**
     * @return array<string, mixed>
     *
     * @throws PayPalApiException
     */
    public function charge(PaymentInterface $payment, PayPalVaultedCredential $credential, string $referenceId, string $requestId): array;
}
