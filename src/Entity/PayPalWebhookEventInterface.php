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

namespace Sylius\PayPalPlugin\Entity;

use Sylius\PayPalPlugin\Model\PayPalWebhookEventStatus;

interface PayPalWebhookEventInterface
{
    public function getEventId(): string;

    public function getPaymentMethodCode(): string;

    public function getEventType(): string;

    public function getStatus(): PayPalWebhookEventStatus;

    public function getCreatedAt(): \DateTimeInterface;

    public function getUpdatedAt(): ?\DateTimeInterface;
}
