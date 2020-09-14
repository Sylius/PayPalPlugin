<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\PaymentInterface;

interface RefundReferenceNumberProviderInterface
{
    public function provide(PaymentInterface $payment): string;
}
