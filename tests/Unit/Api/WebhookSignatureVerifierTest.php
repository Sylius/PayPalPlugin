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

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\WebhookSignatureVerifier;
use Sylius\PayPalPlugin\Provider\HostProviderInterface;
use Symfony\Component\HttpFoundation\Request;

final class WebhookSignatureVerifierTest extends TestCase
{
    private CacheAuthorizeClientApiInterface&MockObject $authorizeClientApi;

    private PaymentMethodInterface&MockObject $paymentMethod;

    /** @var list<array{int, string}> */
    private array $responses = [];

    /** @var list<string> */
    private array $tokens = [];

    /** @var list<array{uri: string, authorization: string, body: string}> */
    private array $sentRequests = [];

    private WebhookSignatureVerifier $webhookSignatureVerifier;

    protected function setUp(): void
    {
        parent::setUp();
        $factory = new Psr17Factory();
        $this->authorizeClientApi = $this->createMock(CacheAuthorizeClientApiInterface::class);
        $this->authorizeClientApi->method('authorize')->willReturnCallback(fn (): string => array_shift($this->tokens) ?? 'TOKEN');
        $this->paymentMethod = $this->paymentMethodInMode(sandbox: true);

        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturnCallback(function (RequestInterface $request) use ($factory): ResponseInterface {
            $this->sentRequests[] = ['uri' => (string) $request->getUri(), 'authorization' => $request->getHeaderLine('Authorization'), 'body' => $request->getBody()->getContents()];
            [$statusCode, $body] = array_shift($this->responses) ?? throw new \LogicException('No more responses to give.');

            return $factory->createResponse($statusCode)->withBody($factory->createStream($body));
        });

        $hostProvider = $this->createStub(HostProviderInterface::class);
        $hostProvider->method('getApiBaseUrlForMode')->willReturnMap([[true, 'https://api.sandbox.paypal.com/'], [false, 'https://api.paypal.com/']]);

        $this->webhookSignatureVerifier = new WebhookSignatureVerifier($client, $this->authorizeClientApi, $hostProvider, $factory, $factory);
    }

    public function test_it_verifies_the_webhook_event_exactly_as_paypal_sent_it(): void
    {
        $this->responses = [[200, '{"verification_status": "SUCCESS"}']];

        self::assertTrue($this->webhookSignatureVerifier->verify($this->signedRequest('{"id":"WH-1",  "resource":{}}'), 'WEBHOOK_ID', $this->paymentMethod));
        self::assertStringEndsWith('"webhook_id":"WEBHOOK_ID","webhook_event":{"id":"WH-1",  "resource":{}}}', $this->sentRequests[0]['body']);
        self::assertSame('Bearer TOKEN', $this->sentRequests[0]['authorization']);
        self::assertSame('https://api.sandbox.paypal.com/v1/notifications/verify-webhook-signature', $this->sentRequests[0]['uri']);
    }

    public function test_it_asks_the_api_of_the_payment_method_mode_to_verify_the_webhook(): void
    {
        $this->responses = [[200, '{"verification_status": "SUCCESS"}']];

        self::assertTrue($this->webhookSignatureVerifier->verify($this->signedRequest('{}'), 'WEBHOOK_ID', $this->paymentMethodInMode(sandbox: false)));
        self::assertSame('https://api.paypal.com/v1/notifications/verify-webhook-signature', $this->sentRequests[0]['uri']);
    }

    public function test_it_rejects_a_webhook_paypal_does_not_verify(): void
    {
        $this->responses = [[200, '{"verification_status": "FAILURE"}']];

        self::assertFalse($this->webhookSignatureVerifier->verify($this->signedRequest('{}'), 'WEBHOOK_ID', $this->paymentMethod));
    }

    public function test_it_rejects_a_webhook_without_signature_headers_without_asking_paypal(): void
    {
        self::assertFalse($this->webhookSignatureVerifier->verify(new Request(content: '{}'), 'WEBHOOK_ID', $this->paymentMethod));
        self::assertSame([], $this->sentRequests);
    }

    public function test_it_verifies_once_more_with_a_fresh_token_when_paypal_rejects_the_cached_one(): void
    {
        $this->tokens = ['REVOKED_TOKEN', 'FRESH_TOKEN'];
        $this->responses = [[401, '{"error": "invalid_token"}'], [200, '{"verification_status": "SUCCESS"}']];
        $this->authorizeClientApi->expects(self::once())->method('invalidate')->with($this->paymentMethod);

        self::assertTrue($this->webhookSignatureVerifier->verify($this->signedRequest('{"id":"WH-1"}'), 'WEBHOOK_ID', $this->paymentMethod));
        self::assertSame(['Bearer REVOKED_TOKEN', 'Bearer FRESH_TOKEN'], array_column($this->sentRequests, 'authorization'));
        self::assertSame($this->sentRequests[0]['body'], $this->sentRequests[1]['body']);
    }

    public function test_it_gives_up_when_paypal_rejects_the_fresh_token_too(): void
    {
        $this->responses = [[401, '{}'], [401, '{}']];

        self::assertFalse($this->webhookSignatureVerifier->verify($this->signedRequest('{}'), 'WEBHOOK_ID', $this->paymentMethod));
        self::assertCount(2, $this->sentRequests);
    }

    private function paymentMethodInMode(bool $sandbox): PaymentMethodInterface&MockObject
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($this->createConfiguredStub(GatewayConfigInterface::class, ['getConfig' => ['sandbox' => $sandbox]]));

        return $paymentMethod;
    }

    private function signedRequest(string $content): Request
    {
        return new Request(server: [
            'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
            'HTTP_PAYPAL_CERT_URL' => 'https://api.sandbox.paypal.com/cert.pem',
            'HTTP_PAYPAL_TRANSMISSION_ID' => 'TRANSMISSION_ID',
            'HTTP_PAYPAL_TRANSMISSION_SIG' => 'SIGNATURE',
            'HTTP_PAYPAL_TRANSMISSION_TIME' => '2026-10-15T10:00:00Z',
        ], content: $content);
    }
}
