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

namespace Sylius\PayPalPlugin\Payum\Factory;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;
use Sylius\PayPalPlugin\Payum\Model\PayPalApi;

final class PayPalGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults([
            'payum.factory_name' => 'pay_pal',
            'payum.factory_title' => 'Pay Pal',
        ]);

        $config['payum.api'] = function (ArrayObject $config) {
            return new PayPalApi($config['api_key']);
        };
    }
}