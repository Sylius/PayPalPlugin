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

        $pointsElsewhere = !$this->webhookUrlProvider->isNotifyUrl($paymentMethod, $registeredUrl);
        $missingEventTypes = WebhookApi::missingEventTypes($registeredEventTypes);

        if (!$pointsElsewhere && [] === $missingEventTypes) {
            return WebhookState::upToDate($registeredUrl);
        }

        return WebhookState::outdated($registeredUrl, $pointsElsewhere, $missingEventTypes);
    }
}
