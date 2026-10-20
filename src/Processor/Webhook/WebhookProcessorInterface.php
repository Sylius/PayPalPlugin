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

use Sylius\PayPalPlugin\Exception\PermanentWebhookFailureInterface;

interface WebhookProcessorInterface
{
    public function supports(string $eventType): bool;

    /**
     * @param array<string, mixed> $payload
     *
     * @throws PermanentWebhookFailureInterface
     */
    public function process(array $payload): void;
}
