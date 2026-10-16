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
use Sylius\PayPalPlugin\Exception\PayPalWebhookResourceNotSupportedException;

final readonly class WebhookResourceApi implements WebhookResourceApiInterface
{
    private const PATHS = [
        'capture' => 'v2/payments/captures/%s',
        'checkout-order' => 'v2/checkout/orders/%s',
        'refund' => 'v2/payments/refunds/%s',
    ];

    public function __construct(private PayPalClientInterface $client)
    {
    }

    public function get(PaymentMethodInterface $paymentMethod, string $resourceType, string $resourceId): array
    {
        $path = self::PATHS[$resourceType] ?? throw new PayPalWebhookResourceNotSupportedException($resourceType);

        return $this->client->get($paymentMethod, sprintf($path, rawurlencode($resourceId)));
    }
}
