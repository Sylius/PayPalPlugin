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
use Sylius\PayPalPlugin\Api\GenericApiInterface;
use Sylius\PayPalPlugin\Api\WebhookApi;
use Sylius\PayPalPlugin\Exception\PayPalApiNotFoundException;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\WebhookState;
use Sylius\PayPalPlugin\Provider\HostProviderInterface;
use Sylius\PayPalPlugin\Provider\WebhookUrlProviderInterface;

final readonly class WebhookStateChecker implements WebhookStateCheckerInterface
{
    private const ALL_EVENT_TYPES = '*';

    public function __construct(
        private GenericApiInterface $genericApi,
        private HostProviderInterface $hostProvider,
        private WebhookUrlProviderInterface $webhookUrlProvider,
    ) {
    }

    public function check(PaymentMethodInterface $paymentMethod): WebhookState
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);
        $webhookId = $config->webhookId();
        if (null === $webhookId || '' === $webhookId) {
            return WebhookState::notRegistered();
        }

        try {
            $webhook = $this->genericApi->get(
                $paymentMethod,
                $this->hostProvider->getApiBaseUrlForMode($config->isSandbox()) . 'v1/notifications/webhooks/' . $webhookId,
            );
        } catch (PayPalApiNotFoundException) {
            return WebhookState::notRegistered();
        }

        $registeredUrl = is_string($webhook['url'] ?? null) ? $webhook['url'] : '';
        $pointsElsewhere = !$this->pointsAtNotifyUrl($paymentMethod, $registeredUrl);
        $missingEventTypes = $this->missingEventTypes($webhook['event_types'] ?? []);

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

    /** @return list<string> */
    private function missingEventTypes(mixed $registeredEventTypes): array
    {
        $names = is_array($registeredEventTypes) ? array_column($registeredEventTypes, 'name') : [];
        if (in_array(self::ALL_EVENT_TYPES, $names, true)) {
            return [];
        }

        return array_values(array_diff(WebhookApi::EVENT_TYPES, $names));
    }

    private function withoutScheme(string $url): string
    {
        return strtolower((string) parse_url($url, \PHP_URL_HOST)) . rtrim((string) parse_url($url, \PHP_URL_PATH), '/');
    }
}
