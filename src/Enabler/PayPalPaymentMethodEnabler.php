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
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Exception\PaymentMethodCouldNotBeEnabledException;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;

final class PayPalPaymentMethodEnabler implements PaymentMethodEnablerInterface
{
    public function __construct(
        private readonly Client|ClientInterface $client,
        private readonly string $baseUrl,
        private readonly ObjectManager $paymentMethodManager,
        private readonly SellerWebhookRegistrarInterface $sellerWebhookRegistrar,
        private readonly ?RequestFactoryInterface $requestFactory = null,
    ) {
        if ($this->client instanceof GuzzleClientInterface) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                'Passing GuzzleHttp\Client as a first argument in the constructor is deprecated and will be prohibited in 2.0. Use Psr\Http\Client\ClientInterface instead.',
            );
        }

        if (null === $this->requestFactory) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                'Not passing $requestFactory to %s constructor is deprecated and will be prohibited in 2.0',
                self::class,
            );
        }
    }

    public function enable(PaymentMethodInterface $paymentMethod): void
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = $gatewayConfig->getConfig();

        if ($this->client instanceof GuzzleClientInterface || null === $this->requestFactory) {
            $response = $this->client->request(
                'GET',
                sprintf('%s/seller-permissions/check/%s', $this->baseUrl, (string) $config['merchant_id']),
            );
        } else {
            $response = $this->client->sendRequest(
                $this->requestFactory->createRequest(
                    'GET',
                    sprintf('%s/seller-permissions/check/%s', $this->baseUrl, (string) $config['merchant_id']),
                ),
            );
        }

        $content = (array) json_decode($response->getBody()->getContents(), true);
        if (!((bool) $content['permissionsGranted'])) {
            throw new PaymentMethodCouldNotBeEnabledException();
        }

        $this->sellerWebhookRegistrar->register($paymentMethod);

        $paymentMethod->setEnabled(true);
        $this->paymentMethodManager->flush();
    }
}
