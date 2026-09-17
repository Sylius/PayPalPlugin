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

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Exception\UnsupportedPayPalPaymentSourceException;

interface PayPalPaymentSourceProviderInterface
{
    public const PAYPAL = 'paypal';

    /**
     * @param array<string, mixed> $experienceContext
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedPayPalPaymentSourceException
     */
    public function provide(OrderInterface $order, string $paymentSource, array $experienceContext): array;

    public function supports(string $paymentSource): bool;
}
