<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Generator;

use Payum\Core\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Webmozart\Assert\Assert;

final class PayPalAuthAssertionGenerator implements PayPalAuthAssertionGeneratorInterface
{
    public function generate(PaymentMethodInterface $paymentMethod): string
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = $gatewayConfig->getConfig();

        Assert::keyExists($config, 'client_id');
        Assert::keyExists($config, 'merchant_id');

        return
            base64_encode('{"alg":"none"}') . '.' .
            base64_encode(
                (string) json_encode(['iss' => (string) $config['client_id'], 'payer_id' => (string) $config['merchant_id']])
            ) . '.'
        ;
    }
}
