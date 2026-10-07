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
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Factory\PayPalOrderFactory;
use Sylius\PayPalPlugin\Factory\PayPalOrderFactoryInterface;
use Sylius\PayPalPlugin\Factory\PurchaseUnitFactory;
use Sylius\PayPalPlugin\Model\PayPalOrder;
use Sylius\PayPalPlugin\Provider\PaymentReferenceNumberProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalItemDataProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;

final readonly class CreateOrderApi implements CreateOrderApiInterface
{
    public const PAYPAL_INTENT_CAPTURE = PayPalOrder::INTENT_CAPTURE;

    public function __construct(
        private PayPalClientInterface $client,
        private PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
        private PayPalItemDataProviderInterface $payPalItemDataProvider,
        private ?PayPalOrderFactoryInterface $payPalOrderFactory = null,
    ) {
        if (null === $this->payPalOrderFactory) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing $payPalOrderFactory to "%s" constructor is deprecated and will be prohibited in 3.0',
                self::class,
            );
        }
    }

    public function create(
        string $token,
        PaymentInterface $payment,
        string $referenceId,
        string $paymentSource = PayPalPaymentSourceProviderInterface::PAYPAL,
        ?string $customId = null,
        ?string $requestId = null,
        ?string $returnUrl = null,
        ?string $cancelUrl = null,
    ): array {
        $payPalOrder = $this->getPayPalOrderFactory()->create(
            $payment,
            $referenceId,
            $paymentSource,
            $customId,
            $returnUrl,
            $cancelUrl,
        );

        return $this->client->post(
            'v2/checkout/orders',
            $token,
            $payPalOrder->toArray(),
            null === $requestId ? [] : ['PayPal-Request-Id' => $requestId],
        );
    }

    private function getPayPalOrderFactory(): PayPalOrderFactoryInterface
    {
        return $this->payPalOrderFactory ?? new PayPalOrderFactory(
            new PurchaseUnitFactory(
                $this->paymentReferenceNumberProvider,
                $this->payPalItemDataProvider,
            ),
        );
    }
}
