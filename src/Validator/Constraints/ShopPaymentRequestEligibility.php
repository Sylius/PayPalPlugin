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

namespace Sylius\PayPalPlugin\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class ShopPaymentRequestEligibility extends Constraint
{
    public string $actionNotAllowedMessage = 'sylius_paypal.payment_request.action_not_allowed';

    public string $paymentMethodNotAvailableMessage = 'sylius_paypal.payment_request.payment_method_not_available';

    public string $payloadInvalidMessage = 'sylius_paypal.payment_request.payload_invalid';

    public string $paymentSourceNotAvailableMessage = 'sylius_paypal.payment_request.payment_source_not_available';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return ShopPaymentRequestEligibilityValidator::class;
    }
}
