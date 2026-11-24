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

namespace Sylius\PayPalPlugin\Twig;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\PayPalPlugin\Checker\PayerActionCheckerInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Model\PayPalVaultRequirement;
use Sylius\PayPalPlugin\Provider\CurrentPayPalLocaleProviderInterface;
use Sylius\PayPalPlugin\Provider\HostProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalVaultRequestProviderInterface;
use Sylius\PayPalPlugin\Provider\ProductVariantVaultRequestProviderInterface;
use Sylius\PayPalPlugin\Provider\WebSdkConfigurationProviderInterface;
use Sylius\PayPalPlugin\Resolver\PayPalPaymentMethodsResolverInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PayPalExtension extends AbstractExtension
{
    private const PARTNER_JS_PATH = '/webapps/merchantboarding/js/lib/lightbox/partner.js';

    public function __construct(
        private readonly PayPalPaymentMethodsResolverInterface $payPalPaymentMethodsResolver,
        private readonly ChannelContextInterface $channelContext,
        private readonly WebSdkConfigurationProviderInterface $webSdkConfigurationProvider,
        private readonly PayerActionCheckerInterface $payerActionChecker,
        private readonly CurrentPayPalLocaleProviderInterface $currentLocaleProvider,
        private readonly HostProviderInterface $hostProvider,
        private readonly ?PayPalVaultRequestProviderInterface $vaultRequestProvider = null,
        private readonly ?ProductVariantVaultRequestProviderInterface $productVariantVaultRequestProvider = null,
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

        if (null === $this->productVariantVaultRequestProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '3.1',
                'Not passing an instance of %s to %s constructor is deprecated and will be required in 4.0.',
                ProductVariantVaultRequestProviderInterface::class,
                self::class,
            );
        }
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sylius_paypal_is_messaging_enabled', [$this, 'isMessagingEnabled']),
            new TwigFunction('sylius_paypal_web_sdk_script_url', [$this, 'getWebSdkScriptUrl']),
            new TwigFunction('sylius_paypal_web_sdk_instance_config', [$this, 'getWebSdkInstanceConfig']),
            new TwigFunction('sylius_paypal_is_awaiting_payer_action', [$this, 'isAwaitingPayerAction']),
            new TwigFunction('sylius_paypal_payer_action_url', [$this, 'getPayerActionUrl']),
            new TwigFunction('sylius_paypal_is_refunded_to_paypal_wallet', [$this, 'isRefundedToPayPalWallet']),
            new TwigFunction('sylius_paypal_partner_js_url', [$this, 'getPartnerJsUrl']),
        ];
    }

    public function isMessagingEnabled(
        ?PaymentMethodInterface $paymentMethod = null,
        ?OrderInterface $order = null,
        ?ProductVariantInterface $variant = null,
    ): bool {
        $paymentMethod ??= $this->channelPaymentMethod();
        $gatewayConfig = $paymentMethod?->getGatewayConfig();
        if (null === $gatewayConfig) {
            return false;
        }

        $config = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);

        if (!$config->isPayLaterEnabled() || !$config->isMessagingEnabled()) {
            return false;
        }

        $vaultRequirement = $this->vaultRequirement($paymentMethod, $order, $variant);

        return null === $vaultRequirement || ($vaultRequirement->isMet() && null === $vaultRequirement->vaultRequest());
    }

    public function getWebSdkScriptUrl(?PaymentMethodInterface $paymentMethod = null): string
    {
        $paymentMethod ??= $this->channelPaymentMethod();
        if (null === $paymentMethod) {
            return '';
        }

        return $this->webSdkConfigurationProvider->getScriptUrl($paymentMethod);
    }

    /** @return array<string, mixed> */
    public function getWebSdkInstanceConfig(string $pageType, ?string $locale = null, ?PaymentMethodInterface $paymentMethod = null): array
    {
        $paymentMethod ??= $this->channelPaymentMethod();
        if (null === $paymentMethod) {
            return [];
        }

        try {
            return $this->webSdkConfigurationProvider->getInstanceConfig(
                $paymentMethod,
                $pageType,
                ['paypal-messages'],
                $locale ?? $this->currentLocaleProvider->provide(),
            );
        } catch (\InvalidArgumentException) {
            return [];
        }
    }

    public function isAwaitingPayerAction(PaymentInterface $payment): bool
    {
        return $this->payerActionChecker->isAwaitingPayerAction($payment);
    }

    public function getPayerActionUrl(PaymentInterface $payment): ?string
    {
        return PayPalPaymentDetails::fromPayment($payment)->payerActionUrl();
    }

    public function isRefundedToPayPalWallet(PaymentInterface $payment): bool
    {
        return PayPalPaymentSourceProviderInterface::PAYPAL === PayPalPaymentDetails::fromPayment($payment)->paymentSource();
    }

    public function getPartnerJsUrl(bool $sandbox = false): string
    {
        return $this->hostProvider->getWebUrlForMode($sandbox) . self::PARTNER_JS_PATH;
    }

    private function vaultRequirement(
        PaymentMethodInterface $paymentMethod,
        ?OrderInterface $order,
        ?ProductVariantInterface $variant,
    ): ?PayPalVaultRequirement {
        if (null !== $order) {
            return $this->vaultRequestProvider?->provide($order, $paymentMethod);
        }

        if (null === $variant) {
            return null;
        }

        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        return $this->productVariantVaultRequestProvider?->provide($variant, $channel, $paymentMethod);
    }

    private function channelPaymentMethod(): ?PaymentMethodInterface
    {
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        return $this->payPalPaymentMethodsResolver->getInChannel($channel)[0] ?? null;
    }
}
