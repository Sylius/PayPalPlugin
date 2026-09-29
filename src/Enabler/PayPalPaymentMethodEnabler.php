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
use Sylius\PayPalPlugin\Exception\PaymentMethodCouldNotBeEnabledException;
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
        $config = $gatewayConfig->getConfig();

        $response = $this->client->sendRequest(
            $this->requestFactory->createRequest(
                'GET',
                sprintf('%s/seller-permissions/check/%s', $this->baseUrl, (string) $config[PayPalGatewayConfig::MERCHANT_ID]),
            ),
        );

        if ($response->getStatusCode() >= 299) {
            throw new PaymentMethodCouldNotBeEnabledException();
        }

        $content = (array) json_decode($response->getBody()->getContents(), true);
        if (!((bool) ($content['permissionsGranted'] ?? false))) {
            throw new PaymentMethodCouldNotBeEnabledException();
        }

        $this->sellerWebhookRegistrar->register($paymentMethod);

        $paymentMethod->setEnabled(true);
        $this->paymentMethodManager->flush();
    }
}
