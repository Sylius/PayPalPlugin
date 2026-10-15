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

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Exception\UntrustedPayPalUrlException;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Provider\HostProviderInterface;

final readonly class GenericApi implements GenericApiInterface
{
    public function __construct(
        private PayPalClientInterface $client,
        private HostProviderInterface $hostProvider,
    ) {
    }

    public function get(PaymentMethodInterface $paymentMethod, string $url): array
    {
        $content = $this->client->get($paymentMethod, $this->pathOf($url, $paymentMethod));

        if ([] === $content || isset($content['name']) || isset($content['error'])) {
            throw new PayPalPluginException();
        }

        return $content;
    }

    private function pathOf(string $url, PaymentMethodInterface $paymentMethod): string
    {
        $parts = parse_url($url);

        if (
            false === $parts ||
            'https' !== strtolower($parts['scheme'] ?? '') ||
            !in_array(strtolower($parts['host'] ?? ''), $this->trustedHosts($paymentMethod), true) ||
            isset($parts['port']) ||
            isset($parts['user']) ||
            isset($parts['pass'])
        ) {
            throw new UntrustedPayPalUrlException($url);
        }

        return ltrim($parts['path'] ?? '', '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }

    /** @return list<string> */
    private function trustedHosts(PaymentMethodInterface $paymentMethod): array
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $sandbox = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->isSandbox();

        $host = strtolower((string) parse_url($this->hostProvider->getApiBaseUrlForMode($sandbox), \PHP_URL_HOST));

        return [$host, (string) preg_replace('/^api\./', 'api-m.', $host)];
    }
}
