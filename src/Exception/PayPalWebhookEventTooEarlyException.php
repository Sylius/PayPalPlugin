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

namespace Sylius\PayPalPlugin\Exception;

use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

final class PayPalWebhookEventTooEarlyException extends RecoverableMessageHandlingException
{
    public function __construct(string $eventType, private readonly int $retryAfter)
    {
        parent::__construct(
            sprintf('PayPal webhook event %s is processed in %d seconds at the earliest.', $eventType, $retryAfter),
            0,
            null,
            $retryAfter * 1000,
        );
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
