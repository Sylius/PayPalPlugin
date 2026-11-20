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

use Sylius\Component\Core\Model\PaymentMethodInterface;

interface VaultingCapabilityWebhookProcessorInterface
{
    public const EVENT_TYPE = 'CUSTOMER.MERCHANT-INTEGRATION.CAPABILITY-UPDATED';

    /** @param array<string, mixed> $resource */
    public function process(array $resource, PaymentMethodInterface $paymentMethod): void;
}
