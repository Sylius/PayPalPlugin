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

interface WebhookUrlProviderInterface
{
    public const ROUTE = 'sylius_payment_method_notify';

    public const LEGACY_PATH = '/paypal-webhook/api/';

    public function provide(PaymentMethodInterface $paymentMethod): string;

    /**
     * @return list<string> the URLs the webhook of the payment method may be registered on: the notify URLs,
     *                      the one provide() returns first, then the legacy URLs
     */
    public function provideCandidates(PaymentMethodInterface $paymentMethod): array;
}
