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

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Factory\PayPalOrderFactoryInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;

final readonly class CreateOrderApi implements CreateOrderApiInterface
{
    public function __construct(
        private PayPalClientInterface $client,
        private PayPalOrderFactoryInterface $payPalOrderFactory,
    ) {
    }

    public function create(
        PaymentMethodInterface $paymentMethod,
        PaymentInterface $payment,
        string $referenceId,
        string $paymentSource = PayPalPaymentSourceProviderInterface::PAYPAL,
        ?string $customId = null,
        ?string $requestId = null,
        ?string $returnUrl = null,
        ?string $cancelUrl = null,
    ): array {
        $payPalOrder = $this->payPalOrderFactory->create(
            $payment,
            $referenceId,
            $paymentSource,
            $customId,
            $returnUrl,
            $cancelUrl,
        );

        return $this->client->post(
            $paymentMethod,
            'v2/checkout/orders',
            $payPalOrder->toArray(),
            null === $requestId ? [] : ['PayPal-Request-Id' => $requestId],
        );
    }
}
