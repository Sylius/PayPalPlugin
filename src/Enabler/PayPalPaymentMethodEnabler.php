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
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\WebhookApiInterface;
use Sylius\PayPalPlugin\Exception\PaymentMethodCouldNotBeEnabledException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookUrlNotValidException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PayPalPaymentMethodEnabler implements PaymentMethodEnablerInterface
{
    /** @var Client */
    private $client;

    /** @var string */
    private $baseUrl;

    /** @var ObjectManager */
    private $paymentMethodManager;

    /** @var WebhookApiInterface */
    private $webhookApi;

    /** @var AuthorizeClientApiInterface */
    private $authorizeClientApi;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    public function __construct(
        Client $client,
        string $baseUrl,
        ObjectManager $paymentMethodManager,
        AuthorizeClientApiInterface $authorizeClientApi,
        WebhookApiInterface $webhookApi,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
        $this->paymentMethodManager = $paymentMethodManager;
        $this->authorizeClientApi = $authorizeClientApi;
        $this->webhookApi = $webhookApi;
        $this->urlGenerator = $urlGenerator;
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

        $token = $this->authorizeClientApi->authorize(
            (string) $config['client_id'], (string) $config['client_secret']
        );

        $webhookUrl = $this->urlGenerator->generate('sylius_paypal_plugin_webhook_refund_order', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $webhookResponse = $this->webhookApi->register($token, $webhookUrl);

        $content = (array) json_decode($response->getBody()->getContents(), true);
        if (!((bool) $content['permissionsGranted'])) {
            throw new PaymentMethodCouldNotBeEnabledException();
        }

        if (array_key_exists('name', $webhookResponse) && $webhookResponse['name'] === 'VALIDATION_ERROR') {
            throw new PayPalWebhookUrlNotValidException();
        }

        $paymentMethod->setEnabled(true);
        $this->paymentMethodManager->flush();
    }
}
