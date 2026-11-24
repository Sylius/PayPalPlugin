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
use Sylius\PayPalPlugin\Checker\PaymentAuthorizationCheckerInterface;
use Sylius\PayPalPlugin\Exception\InvalidPayerDataException;
use Sylius\PayPalPlugin\Model\PayPalOrder;
use Sylius\PayPalPlugin\Model\PayPalVaultedCredential;
use Sylius\PayPalPlugin\Model\PayPalVaultRequest;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;
use Sylius\PayPalPlugin\Provider\ExperienceContextProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalVaultRequestProviderInterface;
use Sylius\PayPalPlugin\Provider\ShippingCallbackUrlProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final readonly class PayPalOrderFactory implements PayPalOrderFactoryInterface
{
    public function __construct(
        private PurchaseUnitFactoryInterface $payPalPurchaseUnitFactory,
        private UrlGeneratorInterface $router,
        private ShippingCallbackUrlProviderInterface $shippingCallbackUrlProvider,
        private ExperienceContextProviderInterface $experienceContextProvider,
        private PayPalPaymentSourceProviderInterface $paymentSourceProvider,
        private PaymentAuthorizationCheckerInterface $paymentAuthorizationChecker,
        private ?PayPalVaultRequestProviderInterface $vaultRequestProvider = null,
    ) {
        if (null === $this->vaultRequestProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '3.1',
                'Not passing an instance of %s to %s constructor is deprecated and will be required in 4.0.',
                PayPalVaultRequestProviderInterface::class,
                self::class,
            );
        }
    }

    public function create(
        PaymentInterface $payment,
        string $referenceId,
        string $paymentSource = PayPalPaymentSourceProviderInterface::PAYPAL,
        ?string $customId = null,
        ?string $returnUrl = null,
        ?string $cancelUrl = null,
    ): PayPalOrder {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $redirectPaymentSource = RedirectPaymentSource::tryFrom($paymentSource);

        $experienceContext = $this->experienceContextProvider->provide(
            $order,
            $returnUrl ?? $this->checkoutCompleteUrl($order, $redirectPaymentSource),
            $cancelUrl ?? $returnUrl ?? $this->checkoutCompleteUrl($order, $redirectPaymentSource),
            null === $redirectPaymentSource ? $this->shippingCallbackUrlProvider->provide() : null,
        );

        $vaultRequest = $this->vaultRequest($order, $payment, $paymentSource);

        /** @phpstan-ignore arguments.count */
        $purchaseUnit = $this->payPalPurchaseUnitFactory->create(
            $payment,
            $referenceId,
            null,
            !isset($experienceContext[PayPalOrder::KEY_ORDER_UPDATE_CALLBACK_CONFIG]),
            $customId,
            $vaultRequest,
        );

        return new PayPalOrder(
            payPalPurchaseUnit: $purchaseUnit,
            intent: $this->intent($payment, $paymentSource),
            paymentSource: $this->paymentSource($payment, $paymentSource, $experienceContext, $vaultRequest),
            processingInstruction: null === $redirectPaymentSource
                ? null
                : PayPalOrder::PROCESSING_INSTRUCTION_ORDER_COMPLETE_ON_PAYMENT_APPROVAL,
        );
    }

    private function vaultRequest(OrderInterface $order, PaymentInterface $payment, string $paymentSource): ?PayPalVaultRequest
    {
        if (!in_array($paymentSource, PayPalVaultedCredential::PAYMENT_SOURCES, true)) {
            return null;
        }

        return $this->vaultRequestProvider?->provide($order, $payment->getMethod())->vaultRequest();
    }

    /**
     * @param array<string, mixed> $experienceContext
     *
     * @return array<string, mixed>
     *
     * @throws InvalidPayerDataException
     */
    private function paymentSource(
        PaymentInterface $payment,
        string $paymentSource,
        array $experienceContext,
        ?PayPalVaultRequest $vaultRequest,
    ): array {
        if (null === $this->vaultRequestProvider) {
            return $this->paymentSourceProvider->provide($payment, $paymentSource, $experienceContext);
        }

        /** @phpstan-ignore arguments.count */
        return $this->paymentSourceProvider->provide($payment, $paymentSource, $experienceContext, $vaultRequest);
    }

    private function intent(PaymentInterface $payment, string $paymentSource): string
    {
        $paymentMethod = $payment->getMethod();
        if (null === $paymentMethod) {
            return PayPalOrder::INTENT_CAPTURE;
        }

        return $this->paymentAuthorizationChecker->isAuthorizable($paymentSource, $paymentMethod)
            ? PayPalOrder::INTENT_AUTHORIZE
            : PayPalOrder::INTENT_CAPTURE
        ;
    }

    private function checkoutCompleteUrl(OrderInterface $order, ?RedirectPaymentSource $redirectPaymentSource): string
    {
        Assert::null(
            $redirectPaymentSource,
            'A redirect PayPal order needs the URL the payer comes back to from the bank.',
        );

        return $this->router->generate(
            'sylius_shop_checkout_complete',
            ['_locale' => $order->getLocaleCode()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
