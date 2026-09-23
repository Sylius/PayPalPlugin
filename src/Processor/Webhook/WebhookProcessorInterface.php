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

namespace Sylius\PayPalPlugin\Processor\Webhook;

interface WebhookProcessorInterface
{
    public function supports(string $eventType): bool;

    /**
     * PayPal replays an event the dispatcher could not finish, so this may run more than once for the same
     * event and must be idempotent. Throw a PermanentWebhookFailureInterface to refuse a replay.
     *
     * @param array<string, mixed> $payload
     */
    public function process(array $payload): void;
}
