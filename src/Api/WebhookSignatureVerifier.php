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
use Psr\Http\Message\StreamFactoryInterface;
use Sylius\PayPalPlugin\Provider\PayPalHostProviderInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class WebhookSignatureVerifier implements WebhookSignatureVerifierInterface
{
    public function __construct(
        private ClientInterface $client,
        private PayPalHostProviderInterface $hostProvider,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public function verify(Request $request, string $webhookId, string $token): bool
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

        $httpRequest = $this->requestFactory
            ->createRequest('POST', $this->hostProvider->getApiBaseUrl() . 'v1/notifications/verify-webhook-signature')
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream($body));

        $response = $this->client->sendRequest($httpRequest);
        $data = (array) json_decode($response->getBody()->getContents(), true);

        return 'SUCCESS' === ($data['verification_status'] ?? null);
    }
}
