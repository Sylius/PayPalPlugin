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
use Sylius\PayPalPlugin\Exception\PayPalApiException;

interface PayPalClientInterface
{
    /** @throws PayPalApiException */
    public function get(PaymentMethodInterface $paymentMethod, string $url): array;

    /** @throws PayPalApiException */
    public function post(PaymentMethodInterface $paymentMethod, string $url, ?array $data = null, array $extraHeaders = []): array;

    /** @throws PayPalApiException */
    public function patch(PaymentMethodInterface $paymentMethod, string $url, ?array $data = null): array;
}
