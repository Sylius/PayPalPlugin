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

interface UpdateWebhookApiInterface
{
    /**
     * @param list<string> $eventTypes
     *
     * @return array<string, mixed>
     *
     * @deprecated since Sylius/PayPalPlugin 2.2 and will be removed in Sylius/PayPalPlugin 3.0.
     */
    public function updateEventTypes(string $token, string $webhookId, array $eventTypes): array;
}
