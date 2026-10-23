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

namespace Sylius\PayPalPlugin\Registrar;

use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\WebhookApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalWebhookAlreadyRegisteredException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookUrlNotValidException;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Provider\WebhookUrlProvider;
use Sylius\PayPalPlugin\Provider\WebhookUrlProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SellerWebhookRegistrar implements SellerWebhookRegistrarInterface
{
    private WebhookUrlProviderInterface $webhookUrlProvider;

    public function __construct(
        private AuthorizeClientApiInterface $authorizeClientApi,
        private UrlGeneratorInterface $urlGenerator,
        private WebhookApiInterface $webhookApi,
        ?WebhookUrlProviderInterface $webhookUrlProvider = null,
    ) {
        $this->webhookUrlProvider = $webhookUrlProvider ?? new WebhookUrlProvider($urlGenerator);
    }

    public function register(PaymentMethodInterface $paymentMethod): void
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);

        $token = $this->authorizeClientApi->authorize($config->clientId(), $config->clientSecret());
        $webhookUrl = $this->webhookUrlProvider->provide();

        try {
            $response = $this->webhookApi->register($token, $webhookUrl);
        } catch (ClientException $exception) {
            /** @var ResponseInterface $exceptionResponse */
            $exceptionResponse = $exception->getResponse();
            /** @var array $exceptionMessage */
            $exceptionMessage = json_decode($exceptionResponse->getBody()->getContents(), true);

            if ($exceptionMessage['name'] === 'WEBHOOK_URL_ALREADY_EXISTS') {
                throw new PayPalWebhookAlreadyRegisteredException();
            }
        }

        if (isset($response['name']) && $response['name'] === 'VALIDATION_ERROR') {
            throw new PayPalWebhookUrlNotValidException();
        }
    }
}
