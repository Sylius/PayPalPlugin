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

namespace Sylius\PayPalPlugin\Refresher;

use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\MerchantOnboardingStatusApiInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Provider\PartnerCredentialsProviderInterface;

final readonly class VaultingCapabilityRefresher implements VaultingCapabilityRefresherInterface
{
    public function __construct(
        private AuthorizeClientApiInterface $authorizeClientApi,
        private MerchantOnboardingStatusApiInterface $merchantOnboardingStatusApi,
        private PartnerCredentialsProviderInterface $partnerCredentialsProvider,
        private ObjectManager $paymentMethodManager,
    ) {
    }

    public function refresh(PaymentMethodInterface $paymentMethod): bool
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (!$gatewayConfig instanceof GatewayConfigInterface) {
            return false;
        }

        $config = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);
        $sandbox = $config->isSandbox();

        $status = $this->merchantOnboardingStatusApi->get(
            $this->authorizeClientApi->authorize($config->clientId(), $config->clientSecret(), $sandbox)->value(),
            $this->partnerCredentialsProvider->provide($sandbox)->getPartnerId(),
            $config->merchantId(),
            $sandbox,
        );

        $vaultingEnabled = $status->isVaultingEnabled();
        if ($vaultingEnabled !== $config->isVaultingEnabled()) {
            $gatewayConfig->setConfig(array_merge(
                $gatewayConfig->getConfig(),
                [PayPalGatewayConfig::VAULTING_ENABLED => $vaultingEnabled],
            ));
            $this->paymentMethodManager->flush();
        }

        return $vaultingEnabled;
    }
}
