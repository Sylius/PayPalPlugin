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
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Creator\PayPalSandboxPaymentMethodCreatorInterface;
use Sylius\PayPalPlugin\Exception\PaymentMethodCouldNotBeEnabledException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookAlreadyRegisteredException;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;

final readonly class PayPalPaymentMethodEnabler implements PaymentMethodEnablerInterface
{
    public function __construct(
        private ClientInterface $client,
        private string $baseUrl,
        private ObjectManager $paymentMethodManager,
        private SellerWebhookRegistrarInterface $sellerWebhookRegistrar,
        private RequestFactoryInterface $requestFactory,
    ) {
    }

    public function enable(PaymentMethodInterface $paymentMethod): void
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);

        if (!$this->isCreatedWithSandboxCredentials($config)) {
            $this->verifyPermissions($config);
        }

        try {
            $this->sellerWebhookRegistrar->register($paymentMethod);
        } catch (PayPalWebhookAlreadyRegisteredException) {
        }

        $paymentMethod->setEnabled(true);
        $this->paymentMethodManager->flush();
    }

    private function verifyPermissions(PayPalGatewayConfig $config): void
    {
        $response = $this->client->sendRequest(
            $this->requestFactory->createRequest(
                'GET',
                sprintf('%s/seller-permissions/check/%s', $this->baseUrl, $config->merchantId()),
            ),
        );

        if ($response->getStatusCode() >= 299) {
            throw new PaymentMethodCouldNotBeEnabledException();
        }

        $content = (array) json_decode($response->getBody()->getContents(), true);
        if (!((bool) ($content['permissionsGranted'] ?? false))) {
            throw new PaymentMethodCouldNotBeEnabledException();
        }
    }

    private function isCreatedWithSandboxCredentials(PayPalGatewayConfig $config): bool
    {
        return
            $config->hasSyliusMerchantId() &&
            PayPalSandboxPaymentMethodCreatorInterface::SYLIUS_SANDBOX_MERCHANT_ID === $config->syliusMerchantId()
        ;
    }
}
