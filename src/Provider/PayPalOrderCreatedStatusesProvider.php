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

final readonly class PayPalOrderCreatedStatusesProvider implements PayPalOrderCreatedStatusesProviderInterface
{
    public const STATUS_CREATED = 'CREATED';

    public const STATUS_PAYER_ACTION_REQUIRED = 'PAYER_ACTION_REQUIRED';

    public function provide(): array
    {
        return [self::STATUS_CREATED, self::STATUS_PAYER_ACTION_REQUIRED];
    }
}
