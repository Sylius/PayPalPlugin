<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Enabler;

use Doctrine\Persistence\ObjectManager;
use GuzzleHttp\Client;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Exception\PaymentMethodCouldNotBeEnabledException;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;

final class PayPalPaymentMethodEnabler implements PaymentMethodEnablerInterface
{
    private Client $client;

    private string $baseUrl;

    private ObjectManager $paymentMethodManager;

    private SellerWebhookRegistrarInterface $sellerWebhookRegistrar;

    public function __construct(
        Client $client,
        string $baseUrl,
        ObjectManager $paymentMethodManager,
        SellerWebhookRegistrarInterface $sellerWebhookRegistrar
    ) {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
        $this->paymentMethodManager = $paymentMethodManager;
        $this->sellerWebhookRegistrar = $sellerWebhookRegistrar;
    }

    public function enable(PaymentMethodInterface $paymentMethod): void
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = $gatewayConfig->getConfig();

        $response = $this->client->request(
            'GET',
            sprintf('%s/seller-permissions/check/%s', $this->baseUrl, (string) $config['merchant_id'])
        );

        $content = (array) json_decode($response->getBody()->getContents(), true);
        if (!((bool) $content['permissionsGranted'])) {
            throw new PaymentMethodCouldNotBeEnabledException();
        }

        $this->sellerWebhookRegistrar->register($paymentMethod);

        $paymentMethod->setEnabled(true);
        $this->paymentMethodManager->flush();
    }
}
