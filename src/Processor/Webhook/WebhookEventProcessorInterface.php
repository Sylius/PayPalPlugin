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

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Exception\PermanentWebhookFailureInterface;

interface WebhookEventProcessorInterface
{
    /**
     * @param array<string, mixed> $resource
     *
     * @throws PermanentWebhookFailureInterface
     */
    public function process(string $eventType, array $resource, PaymentInterface $payment): void;
}
