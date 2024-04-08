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

namespace Sylius\PayPalPlugin\Entity;

use Sylius\Component\Core\Model\PaymentMethodInterface;

interface PayPalCredentialsInterface
{
    public function paymentMethod(): PaymentMethodInterface;

    public function accessToken(): string;

    public function creationTime(): \DateTime;

    public function expirationTime(): \DateTime;

    public function isExpired(): bool;
}
