<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\HttpFoundation\Request;

interface PayPalOrderProviderInterface
{
    public function provide(Request $request): OrderInterface;
}
