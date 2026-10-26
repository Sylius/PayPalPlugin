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
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;

final readonly class OrderDetailsApi implements OrderDetailsApiInterface
{
    public function __construct(private PayPalClientInterface $client)
    {
    }

    public function get(PaymentMethodInterface $paymentMethod, string $orderId): PayPalOrderDetails
    {
        return PayPalOrderDetails::fromArray($this->client->get($paymentMethod, sprintf('v2/checkout/orders/%s', $orderId)));
    }
}
