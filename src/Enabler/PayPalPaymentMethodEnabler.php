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
        $config = $gatewayConfig->getConfig();

        try {
            $partnerId = $this->partnerCredentialsProvider->provide()->getPartnerId();
            $token = $this->authorizeClientApi->authorize((string) $config['client_id'], (string) $config['client_secret']);
            $status = $this->merchantOnboardingStatusApi->get($token, $partnerId, (string) $config['merchant_id']);
        } catch (PayPalPluginException|ClientExceptionInterface|JsonException|InvalidArgumentException) {
            throw new PaymentMethodCouldNotBeEnabledException();
        }

        if (!$status->isComplete()) {
            throw new PaymentMethodCouldNotBeEnabledException();
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
