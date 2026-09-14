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

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PayPalPaymentPageContextProvider implements PayPalPaymentPageContextProviderInterface
{
    public const PAGE_TYPE = 'checkout';

    public const CARD_FIELDS_COMPONENT = 'card-fields';

    public function __construct(
        private PayPalWebSdkConfigurationProviderInterface $webSdkConfigurationProvider,
        private UrlGeneratorInterface $router,
        private LocaleProcessorInterface $localeProcessor,
    ) {
    }

    public function provide(PaymentInterface $payment, string $locale): array
    {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();
        /** @var ChannelInterface $channel */
        $channel = $order->getChannel();

        return [
            'billingAddress' => $order->getBillingAddress(),
            'cancelPayPalPaymentUrl' => $this->router->generate('sylius_paypal_shop_cancel_checkout_payment'),
            'completePayPalOrderUrl' => $this->router->generate(
                'sylius_paypal_shop_complete_paypal_order',
                ['token' => $order->getTokenValue()],
            ),
            'createPayPalOrderUrl' => $this->router->generate(
                'sylius_paypal_shop_create_paypal_order',
                ['token' => $order->getTokenValue()],
            ),
            'currency' => $order->getCurrencyCode(),
            'errorPayPalPaymentUrl' => $this->router->generate('sylius_paypal_shop_payment_error'),
            'order' => $order,
            'payment' => $payment,
            'webSdkInstanceConfig' => $this->webSdkConfigurationProvider->getInstanceConfig(
                $channel,
                self::PAGE_TYPE,
                [...PayPalWebSdkConfigurationProviderInterface::DEFAULT_COMPONENTS, self::CARD_FIELDS_COMPONENT],
                $this->localeProcessor->process($locale),
            ),
            'webSdkScriptUrl' => $this->webSdkConfigurationProvider->getScriptUrl(),
        ];
    }
}
