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

namespace Sylius\PayPalPlugin\Client;

use Sylius\Component\Core\Model\PaymentMethodInterface;

interface PayPalClientInterface
{
    public function get(PaymentMethodInterface $paymentMethod, string $url): array;

    public function post(PaymentMethodInterface $paymentMethod, string $url, ?array $data = null, array $extraHeaders = []): array;

    public function patch(PaymentMethodInterface $paymentMethod, string $url, ?array $data = null): array;
}
