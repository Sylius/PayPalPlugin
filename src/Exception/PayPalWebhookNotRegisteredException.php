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

namespace Sylius\PayPalPlugin\Exception;

final class PayPalWebhookNotRegisteredException extends \RuntimeException
{
    private string $description;

    public function __construct(string $paymentMethodCode, string $webhookUrl, string $reason)
    {
        $this->description = sprintf(
            'PayPal has no webhook for the payment method "%s" and refused to register one at "%s": %s.',
            $paymentMethodCode,
            $webhookUrl,
            $reason,
        );

        parent::__construct($this->description . ' Set the "sylius_paypal.webhook_base_url" parameter when the URL is generated outside a request.');
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
