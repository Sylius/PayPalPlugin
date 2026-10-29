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

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final readonly class UpdateWebhookApi implements UpdateWebhookApiInterface
{
    public function __construct(private PayPalClientInterface $client)
    {
    }

    public function update(PaymentMethodInterface $paymentMethod, string $webhookId, string $webhookUrl, array $eventTypes): array
    {
        return $this->client->patch($paymentMethod, sprintf('v1/notifications/webhooks/%s', $webhookId), [
            [
                'op' => 'replace',
                'path' => '/url',
                'value' => preg_replace('/^http:/i', 'https:', $webhookUrl),
            ],
            [
                'op' => 'replace',
                'path' => '/event_types',
                'value' => array_map(
                    static fn (string $eventType): array => ['name' => $eventType],
                    $eventTypes,
                ),
            ],
        ]);
    }
}
