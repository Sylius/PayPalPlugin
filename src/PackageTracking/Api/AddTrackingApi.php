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

namespace Sylius\PayPalPlugin\PackageTracking\Api;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final readonly class AddTrackingApi implements AddTrackingApiInterface
{
    public function __construct(private PayPalClientInterface $client)
    {
    }

    public function add(PaymentMethodInterface $paymentMethod, string $orderId, array $body): array
    {
        return $this->client->post(
            $paymentMethod,
            sprintf('v2/checkout/orders/%s/track', $orderId),
            $body,
        );
    }
}
