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

namespace Sylius\PayPalPlugin\Processor;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Model\PayPalOrderDetails;

interface PaymentAuthorizationProcessorInterface
{
    /**
     * @return PayPalOrderDetails the PayPal order after the authorization
     *
     * @throws PayPalApiException
     */
    public function authorize(PaymentInterface $payment, ?string $requestId = null): PayPalOrderDetails;
}
