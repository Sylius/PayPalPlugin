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

namespace Sylius\PayPalPlugin\Api;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\AmountUtils;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final readonly class FindEligibleMethodsApi implements FindEligibleMethodsApiInterface
{
    public function __construct(private PayPalClientInterface $client)
    {
    }

    public function find(string $token, PaymentInterface $payment, array $paymentSources): array
    {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        return $this->client->post('v2/payments/find-eligible-methods', $token, [
            'customer' => ['country_code' => $order->getBillingAddress()?->getCountryCode()],
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => $order->getCurrencyCode(),
                        'value' => AmountUtils::toPayPalValue(
                            (int) $payment->getAmount(),
                            (string) $order->getCurrencyCode(),
                        ),
                    ],
                ],
            ],
            'preferences' => [
                'payment_source_constraint' => [
                    'constraint_type' => 'INCLUDE',
                    'payment_sources' => $paymentSources,
                ],
            ],
        ]);
    }
}
