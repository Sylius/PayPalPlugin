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
use Sylius\PayPalPlugin\Provider\ExperienceContextProvider;
use Sylius\PayPalPlugin\Provider\ExperienceContextProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProvider;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalShippingCallbackUrlProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PayPalOrderFactory implements PayPalOrderFactoryInterface
{
    private ExperienceContextProviderInterface $experienceContextProvider;

    private PayPalPaymentSourceProviderInterface $paymentSourceProvider;

    public function __construct(
        private PayPalPurchaseUnitFactoryInterface $payPalPurchaseUnitFactory,
        private ?UrlGeneratorInterface $router = null,
        private ?PayPalShippingCallbackUrlProviderInterface $shippingCallbackUrlProvider = null,
        ?ExperienceContextProviderInterface $experienceContextProvider = null,
        ?PayPalPaymentSourceProviderInterface $paymentSourceProvider = null,
    ) {
        $this->experienceContextProvider = $experienceContextProvider ?? new ExperienceContextProvider();
        $this->paymentSourceProvider = $paymentSourceProvider ?? new PayPalPaymentSourceProvider();
    }

    public function create(
        PaymentInterface $payment,
        string $referenceId,
        string $paymentSource = PayPalPaymentSourceProviderInterface::PAYPAL,
    ): PayPalOrder {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $payerReturnUrl = $this->router?->generate(
            'sylius_shop_checkout_complete',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $experienceContext = $this->experienceContextProvider->provide(
            $order,
            $payerReturnUrl,
            $payerReturnUrl,
            $this->shippingCallbackUrlProvider?->provide(),
        );

        return new PayPalOrder(
            order: $order,
            payPalPurchaseUnit: $this->payPalPurchaseUnitFactory->create($payment, $referenceId),
            intent: PayPalOrder::INTENT_CAPTURE,
            paymentSource: $this->paymentSourceProvider->provide($order, $paymentSource, $experienceContext),
        );
    }
}
