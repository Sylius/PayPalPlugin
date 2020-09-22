<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Api;

use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class WebhookApi implements WebhookApiInterface
{
    /** @var PayPalClientInterface */
    private $client;

    public function __construct(PayPalClientInterface $client)
    {
        $this->client = $client;
    }

    public function register(string $token, string $webhookUrl): array
    {
        $data = [
            'url' => preg_replace('/^http:/i', 'https:', $webhookUrl),
            'event_types' => [
                ['name' => 'PAYMENT.CAPTURE.REFUNDED'],
            ],
        ];

        return $this->client->post('v1/notifications/webhooks', $token, $data);
    }
}
