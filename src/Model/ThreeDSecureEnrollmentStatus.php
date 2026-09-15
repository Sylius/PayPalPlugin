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

enum ThreeDSecureEnrollmentStatus: string
{
    case Ready = 'Y';
    case NotReady = 'N';
    case SystemUnavailable = 'U';
    case Bypassed = 'B';
}
