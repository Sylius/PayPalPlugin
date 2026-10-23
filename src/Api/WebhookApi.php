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

final readonly class WebhookApi implements WebhookApiInterface
{
    /** @var list<string> */
    public const EVENT_TYPES = [
        'CHECKOUT.ORDER.APPROVED',
        'CHECKOUT.ORDER.COMPLETED',
        'PAYMENT.CAPTURE.COMPLETED',
        'PAYMENT.CAPTURE.DENIED',
        'PAYMENT.CAPTURE.DECLINED',
        'PAYMENT.CAPTURE.PENDING',
        'PAYMENT.CAPTURE.REFUNDED',
        'PAYMENT.CAPTURE.REVERSED',
        'PAYMENT.AUTHORIZATION.CREATED',
        'PAYMENT.AUTHORIZATION.VOIDED',
    ];

    public function __construct(private PayPalClientInterface $client)
    {
    }

    public function register(PaymentMethodInterface $paymentMethod, string $webhookUrl): array
    {
        return $this->client->post($paymentMethod, 'v1/notifications/webhooks', [
            'url' => preg_replace('/^http:/i', 'https:', $webhookUrl),
            'event_types' => array_map(
                static fn (string $eventType): array => ['name' => $eventType],
                self::EVENT_TYPES,
            ),
        ]);
    }
}
