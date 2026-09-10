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

use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class PayPalCallbackSignatureVerifier implements PayPalCallbackSignatureVerifierInterface
{
    private const CERT_URL_PATTERN = '#^https://[a-z0-9-]+(\.[a-z0-9-]+)*\.paypal\.com/v2/checkout/callback/certs/[0-9a-f]{16}$#';

    private const MAX_AGE_IN_SECONDS = 300;

    private const CERTIFICATE_LIFETIME_IN_SECONDS = 86400;

    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function verify(Request $request): bool
    {
        $transmissionId = (string) $request->headers->get('paypal-transmission-id', '');
        $transmissionTime = (string) $request->headers->get('paypal-transmission-time', '');
        $signature = (string) $request->headers->get('paypal-transmission-sig', '');
        $certificateUrl = (string) $request->headers->get('paypal-cert-url', '');

        if ('' === $transmissionId || '' === $transmissionTime || '' === $signature || '' === $certificateUrl) {
            return false;
        }

        if (!$this->isWithinReplayWindow($transmissionTime) || 1 !== preg_match(self::CERT_URL_PATTERN, $certificateUrl)) {
            return false;
        }

        $decodedSignature = base64_decode($signature, true);
        if (false === $decodedSignature) {
            return false;
        }

        $publicKey = $this->getPublicKey($certificateUrl);
        if (null === $publicKey) {
            return false;
        }

        $message = sprintf('%s|%s|%s', $transmissionId, $transmissionTime, crc32((string) $request->getContent()));

        return 1 === openssl_verify($message, $decodedSignature, $publicKey, \OPENSSL_ALGO_SHA256);
    }

    private function isWithinReplayWindow(string $transmissionTime): bool
    {
        try {
            $sentAt = new \DateTimeImmutable($transmissionTime);
        } catch (\Exception) {
            return false;
        }

        return abs(time() - $sentAt->getTimestamp()) <= self::MAX_AGE_IN_SECONDS;
    }

    private function getPublicKey(string $certificateUrl): ?\OpenSSLAsymmetricKey
    {
        $certificate = $this->getCertificate($certificateUrl);
        if (null === $certificate) {
            return null;
        }

        $publicKey = openssl_pkey_get_public($certificate);

        return false === $publicKey ? null : $publicKey;
    }

    private function getCertificate(string $certificateUrl): ?string
    {
        $item = $this->cache->getItem('sylius_paypal_callback_certificate_' . sha1($certificateUrl));

        if ($item->isHit()) {
            $cached = $item->get();

            return is_string($cached) && '' !== $cached ? $cached : null;
        }

        try {
            $response = $this->client->sendRequest($this->requestFactory->createRequest('GET', $certificateUrl));
        } catch (ClientExceptionInterface) {
            return null;
        }

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            return null;
        }

        $certificate = $response->getBody()->getContents();
        if ('' === $certificate) {
            return null;
        }

        $item->set($certificate);
        $item->expiresAfter(self::CERTIFICATE_LIFETIME_IN_SECONDS);
        $this->cache->save($item);

        return $certificate;
    }
}
