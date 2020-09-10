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

final class PayPalPaymentMethodEnabler implements PaymentMethodEnablerInterface
{
    /** @var Client */
    private $client;

    /** @var string */
    private $baseUrl;

    /** @var ObjectManager */
    private $paymentMethodManager;

    public function __construct(Client $client, string $baseUrl, ObjectManager $paymentMethodManager)
    {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
        $this->paymentMethodManager = $paymentMethodManager;
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

        $paymentMethod->setEnabled(true);
        $this->paymentMethodManager->flush();
    }
}
