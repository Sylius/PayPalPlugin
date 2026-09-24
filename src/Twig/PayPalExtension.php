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

use Payum\Core\Model\GatewayConfigInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\PayPalPlugin\Checker\PayerActionChecker;
use Sylius\PayPalPlugin\Checker\PayerActionCheckerInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Processor\LocaleProcessorInterface;
use Sylius\PayPalPlugin\Provider\PayPalFundingSourcesConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalWebSdkConfigurationProviderInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PayPalExtension extends AbstractExtension
{
    private readonly PayerActionCheckerInterface $payerActionChecker;

    public function __construct(
        private readonly bool $sandbox,
        private readonly ?PayPalFundingSourcesConfigurationProviderInterface $fundingSourcesConfigurationProvider = null,
        private readonly ?ChannelContextInterface $channelContext = null,
        private readonly ?PayPalWebSdkConfigurationProviderInterface $webSdkConfigurationProvider = null,
        ?PayerActionCheckerInterface $payerActionChecker = null,
        private readonly ?LocaleContextInterface $localeContext = null,
        private readonly ?LocaleProcessorInterface $localeProcessor = null,
    ) {
        $this->payerActionChecker = $payerActionChecker ?? new PayerActionChecker();

        if (null === $this->fundingSourcesConfigurationProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing $fundingSourcesConfigurationProvider to %s constructor is deprecated and will be required in 3.0',
                self::class,
            );
        }
        if (null === $this->channelContext) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing $channelContext to %s constructor is deprecated and will be required in 3.0',
                self::class,
            );
        }
        if (null === $this->webSdkConfigurationProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing $webSdkConfigurationProvider to %s constructor is deprecated and will be required in 3.0',
                self::class,
            );
        }
        if (null === $this->localeContext) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing $localeContext to %s constructor is deprecated and will be required in 3.0',
                self::class,
            );
        }
        if (null === $this->localeProcessor) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing $localeProcessor to %s constructor is deprecated and will be required in 3.0',
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
        ];
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
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
        if (null === $this->webSdkConfigurationProvider) {
            return '';
        }

        return $this->webSdkConfigurationProvider->getScriptUrl();
    }

    /** @return array<string, mixed> */
    public function getWebSdkInstanceConfig(string $pageType, ?string $locale = null): array
    {
        if (null === $this->webSdkConfigurationProvider || null === $this->channelContext) {
            return [];
        }

        try {
            /** @var ChannelInterface $channel */
            $channel = $this->channelContext->getChannel();

            return $this->webSdkConfigurationProvider->getInstanceConfig(
                $channel,
                $pageType,
                ['paypal-messages'],
                $locale ?? $this->resolveLocale(),
            );
        } catch (\InvalidArgumentException) {
            return [];
        }
    }

    private function resolveLocale(): ?string
    {
        if (null === $this->localeContext || null === $this->localeProcessor) {
            return null;
        }

        try {
            return $this->localeProcessor->process($this->localeContext->getLocaleCode());
        } catch (\RuntimeException|\UnexpectedValueException) {
            // No current locale, or one PayPal does not support - render without a locale,
            // same as when no instance config caller passes one explicitly.
            return null;
        }
    }

    public function isAwaitingPayerAction(PaymentInterface $payment): bool
    {
        return $this->payerActionChecker->isAwaitingPayerAction($payment);
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
}
