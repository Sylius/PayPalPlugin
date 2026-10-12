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

namespace Sylius\PayPalPlugin\Generator;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;

final class PayPalAuthAssertionGenerator implements PayPalAuthAssertionGeneratorInterface
{
    public function generate(PaymentMethodInterface $paymentMethod): string
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);

        return
            base64_encode('{"alg":"none"}') . '.' .
            base64_encode(
                (string) json_encode(['iss' => $config->clientId(), 'payer_id' => $config->merchantId()]),
            ) . '.'
        ;
    }
}
