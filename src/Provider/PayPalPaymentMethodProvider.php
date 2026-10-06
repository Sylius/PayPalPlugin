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

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Exception\PayPalPaymentMethodNotFoundException;
use Sylius\PayPalPlugin\Repository\Query\PayPalPaymentMethodQueryInterface;

final readonly class PayPalPaymentMethodProvider implements PayPalPaymentMethodProviderInterface
{
    public function __construct(private PayPalPaymentMethodQueryInterface $payPalPaymentMethodQuery)
    {
    }

    public function provide(): PaymentMethodInterface
    {
        return $this->payPalPaymentMethodQuery->findOne() ?? throw new PayPalPaymentMethodNotFoundException();
    }

    public function exists(): bool
    {
        return $this->payPalPaymentMethodQuery->exists();
    }
}
