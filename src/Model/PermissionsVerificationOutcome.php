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

enum PermissionsVerificationOutcome: string
{
    case UpToDate = 'up_to_date';
    case AwaitingPayPal = 'awaiting_paypal';
    case ConsentRequired = 'consent_required';
}
