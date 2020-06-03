<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin;

use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SyliusPayPalPlugin extends Bundle
{
    use SyliusPluginTrait;
}
