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

namespace Sylius\PayPalPlugin\Creator;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Exception\PayPalWebhookAlreadyRegisteredException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookUrlNotValidException;
use Sylius\PayPalPlugin\Model\SellerOnboardingResult;
use Throwable;

interface PayPalOnboardingPaymentMethodCreatorInterface
{
    public const GATEWAY_NAME = 'sylius_paypal';

    public const PAYMENT_METHOD_CODE = 'PAYPAL';

    public const PAYMENT_METHOD_NAME = 'PayPal';

    public const PAYMENT_METHOD_DESCRIPTION = 'Pay with PayPal';

    /**
     * @throws PayPalWebhookAlreadyRegisteredException
     * @throws PayPalWebhookUrlNotValidException
     * @throws Throwable
     */
    public function create(SellerOnboardingResult $result): PaymentMethodInterface;
}
