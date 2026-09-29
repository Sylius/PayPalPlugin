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
use Webmozart\Assert\Assert;

final class PayPalAuthAssertionGenerator implements PayPalAuthAssertionGeneratorInterface
{
    public function generate(PaymentMethodInterface $paymentMethod): string
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = $gatewayConfig->getConfig();

        Assert::keyExists($config, PayPalGatewayConfig::CLIENT_ID);
        Assert::keyExists($config, PayPalGatewayConfig::MERCHANT_ID);

        return
            base64_encode('{"alg":"none"}') . '.' .
            base64_encode(
                (string) json_encode(['iss' => (string) $config[PayPalGatewayConfig::CLIENT_ID], 'payer_id' => (string) $config[PayPalGatewayConfig::MERCHANT_ID]]),
            ) . '.'
        ;
    }
}
