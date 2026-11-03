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

use Psr\Cache\CacheItemPoolInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\WebhookApi;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\WebhookState;

final readonly class CachingWebhookStateChecker implements WebhookStateCheckerInterface
{
    public function __construct(
        private WebhookStateCheckerInterface $webhookStateChecker,
        private CacheItemPoolInterface $cache,
        private int $lifetime,
    ) {
    }

    public function check(PaymentMethodInterface $paymentMethod): WebhookState
    {
        $item = $this->cache->getItem($this->key($paymentMethod));
        if ($item->isHit()) {
            /** @var array{registered: bool, registered_url: ?string, points_elsewhere: bool, missing_event_types: list<string>} $data */
            $data = $item->get();

            return WebhookState::fromArray($data);
        }

        $state = $this->webhookStateChecker->check($paymentMethod);

        $item->set($state->toArray());
        $item->expiresAfter($this->lifetime);
        $this->cache->save($item);

        return $state;
    }

    private function key(PaymentMethodInterface $paymentMethod): string
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);

        return 'sylius_paypal_webhook_state_' . hash('sha256', implode('|', [
            (string) $paymentMethod->getCode(),
            (string) $config->webhookId(),
            $config->isSandbox() ? 'sandbox' : 'production',
            ...WebhookApi::EVENT_TYPES,
        ]));
    }
}
