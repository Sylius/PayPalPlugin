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

namespace Sylius\PayPalPlugin\TestApplication\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Payment\Model\GatewayConfig as BaseGatewayConfig;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_gateway_config')]
class GatewayConfig extends BaseGatewayConfig
{
    #[ORM\Column(name: 'use_payum', type: 'boolean', options: ['default' => 1])]
    protected bool $usePayum = false;

    public function getUsePayum(): bool
    {
        return $this->usePayum;
    }

    public function setUsePayum(bool $usePayum): void
    {
        $this->usePayum = $usePayum;
    }
}
