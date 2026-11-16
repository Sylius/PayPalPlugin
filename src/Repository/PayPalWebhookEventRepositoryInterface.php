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

namespace Sylius\PayPalPlugin\Repository;

use Sylius\PayPalPlugin\Model\PayPalWebhookEventStatus;

interface PayPalWebhookEventRepositoryInterface
{
    public function claim(string $eventId, string $paymentMethodCode, string $eventType): bool;

    public function markAs(string $eventId, string $paymentMethodCode, PayPalWebhookEventStatus $status): void;

    public function removeCreatedBefore(\DateTimeInterface $date): int;
}
