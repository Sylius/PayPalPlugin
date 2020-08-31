<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Processor;

use Sylius\Component\Core\Model\OrderInterface;

interface PayPalAddressProcessorInterface
{
    /**
     * @param array<string, string> $address
     */
    public function process(array $address, OrderInterface $order): void;
}
