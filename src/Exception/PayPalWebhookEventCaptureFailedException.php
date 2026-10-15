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

final class PayPalWebhookEventCaptureFailedException extends RecoverableMessageHandlingException
{
    public function __construct(string $eventType, string $paymentRequestId, string $reason)
    {
        parent::__construct(sprintf(
            'PayPal webhook event %s could not capture the order of Payment Request %s: %s',
            $eventType,
            $paymentRequestId,
            $reason,
        ));
    }
}
