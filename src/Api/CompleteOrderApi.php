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

final readonly class CompleteOrderApi implements CompleteOrderApiInterface
{
    public function __construct(private PayPalClientInterface $client)
    {
    }

    public function complete(PaymentMethodInterface $paymentMethod, string $orderId): array
    {
        return $this->client->post($paymentMethod, sprintf('v2/checkout/orders/%s/capture', $orderId));
    }
}
