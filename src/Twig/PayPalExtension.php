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
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PayPalExtension extends AbstractExtension
{
    public function __construct(
        private readonly bool $sandbox,
        private readonly string $partnerJsUrl = '',
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sylius_is_paypal_enabled', [$this, 'isPayPalEnabled']),
            new TwigFunction('sylius_is_paypal_sandbox', [$this, 'isSandbox']),
            new TwigFunction('sylius_paypal_partner_js_url', [$this, 'getPartnerJsUrl']),
        ];
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    public function getPartnerJsUrl(): string
    {
        return $this->partnerJsUrl;
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
