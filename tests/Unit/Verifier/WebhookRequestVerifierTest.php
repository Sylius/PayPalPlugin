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

namespace Tests\Sylius\PayPalPlugin\Unit\Verifier;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\WebhookSignatureVerifierInterface;
use Sylius\PayPalPlugin\Provider\WebhookIdProviderInterface;
use Sylius\PayPalPlugin\Verifier\WebhookRequestVerifier;
use Sylius\PayPalPlugin\Verifier\WebhookRequestVerifierInterface;
use Symfony\Component\HttpFoundation\Request;

final class WebhookRequestVerifierTest extends TestCase
{
    private WebhookIdProviderInterface&MockObject $webhookIdProvider;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private WebhookSignatureVerifierInterface&MockObject $webhookSignatureVerifier;

    private Request $request;

    private WebhookRequestVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->webhookIdProvider = $this->createMock(WebhookIdProviderInterface::class);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->webhookSignatureVerifier = $this->createMock(WebhookSignatureVerifierInterface::class);

        $this->request = new Request();

        $this->verifier = new WebhookRequestVerifier(
            $this->webhookIdProvider,
            $this->webhookSignatureVerifier,
        );
    }

    public function test_it_implements_webhook_request_verifier_interface(): void
    {
        self::assertInstanceOf(WebhookRequestVerifierInterface::class, $this->verifier);
    }

    public function test_it_verifies_a_request_against_the_webhook_of_the_given_payment_method(): void
    {
        $this->webhookIdProvider->expects(self::once())->method('provide')->with($this->paymentMethod)->willReturn('WEBHOOK_ID');
        $this->webhookSignatureVerifier->method('verify')->with($this->request, 'WEBHOOK_ID', $this->paymentMethod)->willReturn(true);

        self::assertTrue($this->verifier->verify($this->request, $this->paymentMethod));
    }

    public function test_it_tries_again_with_a_refreshed_webhook_id(): void
    {
        $this->webhookIdProvider->method('provide')->willReturn('STALE_WEBHOOK_ID');
        $this->webhookIdProvider->expects(self::once())->method('refresh')->willReturn('FRESH_WEBHOOK_ID');
        $this->webhookSignatureVerifier
            ->method('verify')
            ->willReturnCallback(static fn (Request $request, string $webhookId): bool => 'FRESH_WEBHOOK_ID' === $webhookId)
        ;

        self::assertTrue($this->verifier->verify($this->request, $this->paymentMethod));
    }

    public function test_it_does_not_try_again_with_the_same_webhook_id(): void
    {
        $this->webhookIdProvider->method('provide')->willReturn('WEBHOOK_ID');
        $this->webhookIdProvider->method('refresh')->willReturn('WEBHOOK_ID');
        $this->webhookSignatureVerifier->expects(self::once())->method('verify')->willReturn(false);

        self::assertFalse($this->verifier->verify($this->request, $this->paymentMethod));
    }

    public function test_it_refuses_a_request_it_could_not_check(): void
    {
        $this->webhookIdProvider->method('provide')->willThrowException(new \RuntimeException('PayPal is down'));

        self::assertFalse($this->verifier->verify($this->request, $this->paymentMethod));
    }

    public function test_it_refuses_a_request_when_no_webhook_is_registered(): void
    {
        $this->webhookIdProvider->method('provide')->willReturn(null);
        $this->webhookIdProvider->method('refresh')->willReturn(null);

        $this->webhookSignatureVerifier->expects(self::never())->method('verify');

        self::assertFalse($this->verifier->verify($this->request, $this->paymentMethod));
    }
}
