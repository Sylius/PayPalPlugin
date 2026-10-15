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

namespace Sylius\PayPalPlugin\Api;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Provider\HostProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class WebhookSignatureVerifier implements WebhookSignatureVerifierInterface
{
    public function __construct(
        private ClientInterface $client,
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private HostProviderInterface $hostProvider,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public function verify(Request $request, string $webhookId, PaymentMethodInterface $paymentMethod): bool
    {
        $signatureHeaders = [
            'auth_algo' => $request->headers->get('PAYPAL-AUTH-ALGO'),
            'cert_url' => $request->headers->get('PAYPAL-CERT-URL'),
            'transmission_id' => $request->headers->get('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig' => $request->headers->get('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $request->headers->get('PAYPAL-TRANSMISSION-TIME'),
        ];

        foreach ($signatureHeaders as $value) {
            if (null === $value || '' === $value) {
                return false;
            }
        }

        $wrapper = (string) json_encode($signatureHeaders + ['webhook_id' => $webhookId]);
        $body = substr($wrapper, 0, -1) . ',"webhook_event":' . $request->getContent() . '}';

        $response = $this->send($body, $paymentMethod);
        if (Response::HTTP_UNAUTHORIZED === $response->getStatusCode()) {
            $this->authorizeClientApi->invalidate($paymentMethod);
            $response = $this->send($body, $paymentMethod);
        }

        $data = (array) json_decode($response->getBody()->getContents(), true);

        return 'SUCCESS' === ($data['verification_status'] ?? null);
    }

    private function send(string $body, PaymentMethodInterface $paymentMethod): ResponseInterface
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $sandbox = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->isSandbox();

        $request = $this->requestFactory
            ->createRequest('POST', $this->hostProvider->getApiBaseUrlForMode($sandbox) . 'v1/notifications/verify-webhook-signature')
            ->withHeader('Authorization', 'Bearer ' . $this->authorizeClientApi->authorize($paymentMethod))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream($body));

        return $this->client->sendRequest($request);
    }
}
