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

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Api\FindEligibleMethodsApiInterface;

final class FakeFindEligibleMethodsApi implements FindEligibleMethodsApiInterface
{
    public static array $eligibleMethods = ['trustly' => []];

    public function find(string $token, PaymentInterface $payment, array $paymentSources): array
    {
        return ['eligible_methods' => self::$eligibleMethods];
    }
}
