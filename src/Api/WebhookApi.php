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
use Sylius\PayPalPlugin\Model\WebhookEventTypes;

final readonly class WebhookApi implements WebhookApiInterface
{
    public function __construct(private PayPalClientInterface $client)
    {
    }

    public function register(PaymentMethodInterface $paymentMethod, string $webhookUrl): array
    {
        return $this->client->post($paymentMethod, 'v1/notifications/webhooks', [
            'url' => $webhookUrl,
            'event_types' => WebhookEventTypes::handled()->toPayPal(),
        ]);
    }
}
