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

namespace Sylius\PayPalPlugin\Enabler;

use Doctrine\Persistence\ObjectManager;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\MerchantOnboardingStatusApiInterface;
use Sylius\PayPalPlugin\Exception\PaymentMethodCouldNotBeEnabledException;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookAlreadyRegisteredException;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Provider\PartnerCredentialsProviderInterface;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;

final readonly class PayPalPaymentMethodEnabler implements PaymentMethodEnablerInterface
{
    public function __construct(
        private AuthorizeClientApiInterface $authorizeClientApi,
        private MerchantOnboardingStatusApiInterface $merchantOnboardingStatusApi,
        private ObjectManager $paymentMethodManager,
        private SellerWebhookRegistrarInterface $sellerWebhookRegistrar,
        private PartnerCredentialsProviderInterface $partnerCredentialsProvider,
    ) {
    }

    public function enable(PaymentMethodInterface $paymentMethod): void
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);

        try {
            $partnerId = $this->partnerCredentialsProvider->provide()->getPartnerId();
            $token = $this->authorizeClientApi->authorize($config->clientId(), $config->clientSecret());
            $status = $this->merchantOnboardingStatusApi->get($token, $partnerId, $config->merchantId());
        } catch (PayPalPluginException|ClientExceptionInterface|JsonException|InvalidArgumentException) {
            throw new PaymentMethodCouldNotBeEnabledException();
        }

        if (!$status->isComplete()) {
            throw new PaymentMethodCouldNotBeEnabledException($status);
        }

        try {
            $this->sellerWebhookRegistrar->register($paymentMethod);
        } catch (PayPalWebhookAlreadyRegisteredException) {
            // the webhook is already registered from a previous attempt; nothing to do
        }

        $paymentMethod->setEnabled(true);
        $this->paymentMethodManager->flush();
    }
}
