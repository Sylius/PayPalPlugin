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

namespace Tests\Sylius\PayPalPlugin\Behat\Element;

interface PaymentMethodListElementInterface
{
    public function hasPaymentSource(string $paymentMethodName, string $paymentSource): bool;

    public function getMode(string $paymentMethodName): ?string;

    public function getMorePaymentSources(string $paymentMethodName): ?string;

    public function hasSuggestedPayPalPaymentMethod(): bool;
}
