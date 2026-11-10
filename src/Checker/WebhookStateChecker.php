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

namespace Sylius\PayPalPlugin\Checker;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\WebhookApi;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\WebhookState;
use Sylius\PayPalPlugin\Provider\WebhookUrlProviderInterface;

final readonly class WebhookStateChecker implements WebhookStateCheckerInterface
{
    public function __construct(private WebhookUrlProviderInterface $webhookUrlProvider)
    {
    }

    public function check(PaymentMethodInterface $paymentMethod): WebhookState
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);
        $registeredUrl = $config->webhookUrl();
        $registeredEventTypes = $config->webhookEventTypes();

        if (null === $registeredUrl || null === $registeredEventTypes) {
            return WebhookState::unknown();
        }

        $pointsElsewhere = !$this->pointsAtNotifyUrl($paymentMethod, $registeredUrl);
        $missingEventTypes = array_values(array_diff(WebhookApi::EVENT_TYPES, $registeredEventTypes));

        if (!$pointsElsewhere && [] === $missingEventTypes) {
            return WebhookState::upToDate($registeredUrl);
        }

        return WebhookState::outdated($registeredUrl, $pointsElsewhere, $missingEventTypes);
    }

    private function pointsAtNotifyUrl(PaymentMethodInterface $paymentMethod, string $registeredUrl): bool
    {
        foreach ($this->webhookUrlProvider->provideCandidates($paymentMethod) as $candidate) {
            if (str_ends_with(rtrim($candidate, '/'), rtrim(WebhookUrlProviderInterface::LEGACY_PATH, '/'))) {
                continue;
            }

            if ($this->withoutScheme($candidate) === $this->withoutScheme($registeredUrl)) {
                return true;
            }
        }

        return false;
    }

    private function withoutScheme(string $url): string
    {
        return strtolower((string) parse_url($url, \PHP_URL_HOST)) . rtrim((string) parse_url($url, \PHP_URL_PATH), '/');
    }
}
