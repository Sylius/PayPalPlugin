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
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Checker\PayerActionChecker;
use Sylius\PayPalPlugin\Checker\PayerActionCheckerInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Factory\ModeSwitchViewFactoryInterface;
use Sylius\PayPalPlugin\Model\ModeSwitchView;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Provider\ActiveModeProviderInterface;
use Sylius\PayPalPlugin\Provider\CurrentPayPalLocaleProviderInterface;
use Sylius\PayPalPlugin\Provider\HostProvider;
use Sylius\PayPalPlugin\Provider\HostProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalFundingSourcesConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Sylius\PayPalPlugin\Provider\WebSdkConfigurationProviderInterface;
use Sylius\PayPalPlugin\Resolver\PayPalPaymentMethodsResolverInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PayPalExtension extends AbstractExtension
{
    private const PARTNER_JS_PATH = '/webapps/merchantboarding/js/lib/lightbox/partner.js';

    private readonly PayerActionCheckerInterface $payerActionChecker;

    private readonly HostProviderInterface $hostProvider;

    public function __construct(
        private readonly ActiveModeProviderInterface $activeModeProvider,
        private readonly ModeSwitchViewFactoryInterface $modeSwitchViewFactory,
        private readonly ?PayPalFundingSourcesConfigurationProviderInterface $fundingSourcesConfigurationProvider = null,
        private readonly ?ChannelContextInterface $channelContext = null,
        private readonly ?WebSdkConfigurationProviderInterface $webSdkConfigurationProvider = null,
        ?PayerActionCheckerInterface $payerActionChecker = null,
        private readonly ?CurrentPayPalLocaleProviderInterface $currentLocaleProvider = null,
        ?HostProviderInterface $hostProvider = null,
        private readonly ?PayPalPaymentMethodsResolverInterface $payPalPaymentMethodsResolver = null,
    ) {
        $this->payerActionChecker = $payerActionChecker ?? new PayerActionChecker();
        $this->hostProvider = $hostProvider ?? new HostProvider($this->activeModeProvider);

        if (null === $this->fundingSourcesConfigurationProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.2',
                'Not passing $fundingSourcesConfigurationProvider to %s constructor is deprecated and will be required in 3.0',
                self::class,
            );
        }
        if (null === $this->channelContext) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.2',
                'Not passing $channelContext to %s constructor is deprecated and will be required in 3.0',
                self::class,
            );
        }
        if (null === $this->webSdkConfigurationProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.2',
                'Not passing $webSdkConfigurationProvider to %s constructor is deprecated and will be required in 3.0',
                self::class,
            );
        }
        if (null === $this->currentLocaleProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.2',
                'Not passing $currentLocaleProvider to %s constructor is deprecated and will be required in 3.0',
                self::class,
            );
        }
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sylius_is_paypal_enabled', [$this, 'isPayPalEnabled']),
            new TwigFunction('sylius_is_paypal_sandbox', [$this, 'isSandbox']),
            new TwigFunction('sylius_paypal_is_messaging_enabled', [$this, 'isMessagingEnabled']),
            new TwigFunction('sylius_paypal_web_sdk_script_url', [$this, 'getWebSdkScriptUrl']),
            new TwigFunction('sylius_paypal_web_sdk_instance_config', [$this, 'getWebSdkInstanceConfig']),
            new TwigFunction('sylius_paypal_is_awaiting_payer_action', [$this, 'isAwaitingPayerAction']),
            new TwigFunction('sylius_paypal_payer_action_url', [$this, 'getPayerActionUrl']),
            new TwigFunction('sylius_paypal_is_refunded_to_paypal_wallet', [$this, 'isRefundedToPayPalWallet']),
            new TwigFunction('sylius_paypal_partner_js_url', [$this, 'getPartnerJsUrl']),
            new TwigFunction('sylius_paypal_mode_switch_view', [$this, 'getModeSwitchView']),
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public function getModeSwitchView(array $config): ModeSwitchView
    {
        return $this->modeSwitchViewFactory->createFromConfig($config);
    }

    public function isSandbox(): bool
    {
        return $this->activeModeProvider->isSandbox();
    }

    public function isMessagingEnabled(): bool
    {
        if (null === $this->fundingSourcesConfigurationProvider || null === $this->channelContext) {
            return false;
        }

        try {
            /** @var ChannelInterface $channel */
            $channel = $this->channelContext->getChannel();

            return $this->fundingSourcesConfigurationProvider->isMessagingEnabled($channel);
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public function getWebSdkScriptUrl(): string
    {
        $paymentMethod = $this->channelPaymentMethod();
        if (null === $this->webSdkConfigurationProvider || null === $paymentMethod) {
            return '';
        }

        return $this->webSdkConfigurationProvider->getScriptUrl($paymentMethod);
    }

    /** @return array<string, mixed> */
    public function getWebSdkInstanceConfig(string $pageType, ?string $locale = null): array
    {
        $paymentMethod = $this->channelPaymentMethod();
        if (null === $this->webSdkConfigurationProvider || null === $paymentMethod) {
            return [];
        }

        try {
            return $this->webSdkConfigurationProvider->getInstanceConfig(
                $paymentMethod,
                $pageType,
                ['paypal-messages'],
                $locale ?? $this->currentLocaleProvider?->provide(),
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

    public function isPayPalEnabled(iterable $paymentMethods): bool
    {
        /** @var PaymentMethodInterface $paymentMethod */
        foreach ($paymentMethods as $paymentMethod) {
            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $paymentMethod->getGatewayConfig();
            if ($gatewayConfig->getFactoryName() === SyliusPayPalExtension::PAYPAL_FACTORY_NAME) {
                return true;
            }
        }

        return false;
    }

    private function channelPaymentMethod(): ?PaymentMethodInterface
    {
        if (null === $this->payPalPaymentMethodsResolver || null === $this->channelContext) {
            return null;
        }

        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        return $this->payPalPaymentMethodsResolver->getInChannel($channel)[0] ?? null;
    }
}
