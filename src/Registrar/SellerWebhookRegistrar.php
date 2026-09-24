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
use Sylius\PayPalPlugin\Provider\PayPalWebhookUrlProvider;
use Sylius\PayPalPlugin\Provider\PayPalWebhookUrlProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SellerWebhookRegistrar implements SellerWebhookRegistrarInterface
{
    private PayPalWebhookUrlProviderInterface $webhookUrlProvider;

    public function __construct(
        private AuthorizeClientApiInterface $authorizeClientApi,
        private UrlGeneratorInterface $urlGenerator,
        private WebhookApiInterface $webhookApi,
        ?PayPalWebhookUrlProviderInterface $webhookUrlProvider = null,
    ) {
        $this->webhookUrlProvider = $webhookUrlProvider ?? new PayPalWebhookUrlProvider($urlGenerator);
    }

    public function register(PaymentMethodInterface $paymentMethod): void
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = $gatewayConfig->getConfig();

        $token = $this->authorizeClientApi->authorize((string) $config['client_id'], (string) $config['client_secret']);
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
