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

namespace Tests\Sylius\PayPalPlugin\Unit\Api;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sylius\PayPalPlugin\Api\PayPalCallbackSignatureVerifier;
use Sylius\PayPalPlugin\Api\PayPalCallbackSignatureVerifierInterface;
use Symfony\Component\HttpFoundation\Request;

final class PayPalCallbackSignatureVerifierTest extends TestCase
{
    private const CERT_URL = 'https://api-m.sandbox.paypal.com/v2/checkout/callback/certs/0123456789abcdef';

    private const BODY = '{"id":"1AB23456CD7890123","purchase_units":[{"reference_id":"11111111-2222-3333-4444-555555555555"}]}';

    private const TRANSMISSION_ID = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

    private const CERTIFICATE_LIFETIME = 86400;

    private static \OpenSSLAsymmetricKey $privateKey;

    private static string $certificate;

    private ClientInterface&MockObject $client;

    private RequestFactoryInterface&MockObject $requestFactory;

    private CacheItemPoolInterface&MockObject $cache;

    private CacheItemInterface&MockObject $cacheItem;

    private PayPalCallbackSignatureVerifier $verifier;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertInstanceOf(\OpenSSLAsymmetricKey::class, $key);

        $csr = openssl_csr_new(['commonName' => 'ordercallbackverificationcerts.sandbox.paypal.com'], $key, ['digest_alg' => 'sha256']);
        $certificate = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);
        openssl_x509_export($certificate, $exported);

        self::$privateKey = $key;
        self::$certificate = $exported;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->cacheItem = $this->createMock(CacheItemInterface::class);

        $this->cache->method('getItem')->willReturn($this->cacheItem);
        $this->cacheItem->method('set')->willReturnSelf();
        $this->cacheItem->method('expiresAfter')->willReturnSelf();

        $this->verifier = new PayPalCallbackSignatureVerifier(
            $this->client,
            $this->requestFactory,
            $this->cache,
            self::CERTIFICATE_LIFETIME,
        );
    }

    public function test_it_implements_paypal_callback_signature_verifier_interface(): void
    {
        self::assertInstanceOf(PayPalCallbackSignatureVerifierInterface::class, $this->verifier);
    }

    public function test_it_verifies_a_request_signed_by_paypal(): void
    {
        $this->cacheItem->method('isHit')->willReturn(false);
        $this->mockCertificateDownload(self::$certificate);

        self::assertTrue($this->verifier->verify($this->signedRequest()));
    }

    public function test_it_rejects_a_request_missing_any_transmission_header(): void
    {
        $this->cacheItem->method('isHit')->willReturn(false);
        $this->client->expects(self::never())->method('sendRequest');

        foreach (['PAYPAL_TRANSMISSION_ID', 'PAYPAL_TRANSMISSION_TIME', 'PAYPAL_TRANSMISSION_SIG', 'PAYPAL_CERT_URL'] as $header) {
            self::assertFalse(
                $this->verifier->verify($this->signedRequest(headersToRemove: [$header])),
                sprintf('Expected a request without "%s" to be rejected', $header),
            );
        }
    }

    public function test_it_rejects_a_transmission_older_than_the_replay_window(): void
    {
        $this->cacheItem->method('isHit')->willReturn(false);
        $this->client->expects(self::never())->method('sendRequest');

        $transmissionTime = (new \DateTimeImmutable('-10 minutes'))->format('Y-m-d\TH:i:s\Z');

        self::assertFalse($this->verifier->verify($this->signedRequest(transmissionTime: $transmissionTime)));
    }

    public function test_it_rejects_a_certificate_url_outside_paypal(): void
    {
        $this->cacheItem->method('isHit')->willReturn(false);
        $this->client->expects(self::never())->method('sendRequest');

        $urls = [
            'https://attacker.example.com/v2/checkout/callback/certs/0123456789abcdef',
            'https://api-m.sandbox.paypal.com.attacker.example.com/v2/checkout/callback/certs/0123456789abcdef',
            'http://api-m.sandbox.paypal.com/v2/checkout/callback/certs/0123456789abcdef',
            'https://api-m.sandbox.paypal.com/v1/notifications/certs/CERT-0123456789',
        ];

        foreach ($urls as $url) {
            self::assertFalse(
                $this->verifier->verify($this->signedRequest(certificateUrl: $url)),
                sprintf('Expected "%s" to be rejected', $url),
            );
        }
    }

    public function test_it_rejects_a_body_that_was_tampered_with(): void
    {
        $this->cacheItem->method('isHit')->willReturn(false);
        $this->mockCertificateDownload(self::$certificate);

        $request = $this->signedRequest();
        $tampered = new Request([], [], [], [], [], $request->server->all(), self::BODY . ' ');

        self::assertFalse($this->verifier->verify($tampered));
    }

    public function test_it_rejects_a_signature_that_is_not_valid_base64(): void
    {
        $this->cacheItem->method('isHit')->willReturn(false);
        $this->client->expects(self::never())->method('sendRequest');

        self::assertFalse($this->verifier->verify($this->signedRequest(signature: 'not base64 $$$')));
    }

    public function test_it_rejects_the_request_when_the_certificate_cannot_be_downloaded(): void
    {
        $this->cacheItem->method('isHit')->willReturn(false);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);
        $this->requestFactory->method('createRequest')->willReturn($this->createMock(RequestInterface::class));
        $this->client->method('sendRequest')->willReturn($response);

        self::assertFalse($this->verifier->verify($this->signedRequest()));
    }

    public function test_it_reuses_a_cached_certificate(): void
    {
        $this->cacheItem->method('isHit')->willReturn(true);
        $this->cacheItem->method('get')->willReturn(self::$certificate);
        $this->client->expects(self::never())->method('sendRequest');

        self::assertTrue($this->verifier->verify($this->signedRequest()));
    }

    public function test_it_caches_the_downloaded_certificate_for_the_configured_lifetime(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cacheItem = $this->createMock(CacheItemInterface::class);

        $cacheItem->method('isHit')->willReturn(false);
        $cache->expects(self::once())->method('getItem')->with('sylius_paypal_callback_certificate_' . sha1(self::CERT_URL))->willReturn($cacheItem);
        $cacheItem->expects(self::once())->method('set')->with(self::$certificate)->willReturnSelf();
        $cacheItem->expects(self::once())->method('expiresAfter')->with(self::CERTIFICATE_LIFETIME)->willReturnSelf();
        $cache->expects(self::once())->method('save')->with($cacheItem);

        $this->mockCertificateDownload(self::$certificate);

        $verifier = new PayPalCallbackSignatureVerifier(
            $this->client,
            $this->requestFactory,
            $cache,
            self::CERTIFICATE_LIFETIME,
        );

        self::assertTrue($verifier->verify($this->signedRequest()));
    }

    private function mockCertificateDownload(string $certificate): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn($certificate);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $this->requestFactory
            ->method('createRequest')
            ->with('GET', self::CERT_URL)
            ->willReturn($this->createMock(RequestInterface::class));
        $this->client->method('sendRequest')->willReturn($response);
    }

    /** @param string[] $headersToRemove */
    private function signedRequest(
        ?string $transmissionTime = null,
        ?string $certificateUrl = null,
        ?string $signature = null,
        array $headersToRemove = [],
    ): Request {
        $transmissionTime ??= (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z');

        if (null === $signature) {
            $message = sprintf('%s|%s|%s', self::TRANSMISSION_ID, $transmissionTime, crc32(self::BODY));
            openssl_sign($message, $rawSignature, self::$privateKey, \OPENSSL_ALGO_SHA256);
            $signature = base64_encode($rawSignature);
        }

        $server = [
            'HTTP_PAYPAL_TRANSMISSION_ID' => self::TRANSMISSION_ID,
            'HTTP_PAYPAL_TRANSMISSION_TIME' => $transmissionTime,
            'HTTP_PAYPAL_TRANSMISSION_SIG' => $signature,
            'HTTP_PAYPAL_CERT_URL' => $certificateUrl ?? self::CERT_URL,
        ];

        foreach ($headersToRemove as $header) {
            unset($server['HTTP_' . $header]);
        }

        return new Request([], [], [], [], [], $server, self::BODY);
    }
}
