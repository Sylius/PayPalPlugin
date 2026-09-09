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

namespace Sylius\PayPalPlugin\Factory;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Model\PayPalOrder;
use Sylius\PayPalPlugin\Provider\PayPalShippingCallbackUrlProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PayPalOrderFactory implements PayPalOrderFactoryInterface
{
    public function __construct(
        private PayPalPurchaseUnitFactoryInterface $payPalPurchaseUnitFactory,
        private ?UrlGeneratorInterface $router = null,
        private ?PayPalShippingCallbackUrlProviderInterface $shippingCallbackUrlProvider = null,
    ) {
    }

    public function create(PaymentInterface $payment, string $referenceId): PayPalOrder
    {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $payerReturnUrl = $this->router?->generate(
            'sylius_shop_checkout_complete',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return new PayPalOrder(
            $order,
            $this->payPalPurchaseUnitFactory->create($payment, $referenceId),
            PayPalOrder::INTENT_CAPTURE,
            $payerReturnUrl,
            $payerReturnUrl,
            $this->shippingCallbackUrlProvider?->provide(),
        );
    }
}
