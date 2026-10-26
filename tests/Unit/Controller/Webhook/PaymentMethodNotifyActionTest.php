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

namespace Tests\Sylius\PayPalPlugin\Unit\Controller\Webhook;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\Controller\Webhook\PaymentMethodNotifyAction;
use Sylius\PayPalPlugin\Exception\PayPalWebhookEventIgnoredException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookSignatureNotVerifiedException;
use Sylius\PayPalPlugin\Processor\Webhook\VaultingCapabilityWebhookProcessorInterface;
use Sylius\PayPalPlugin\Verifier\WebhookRequestVerifierInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PaymentMethodNotifyActionTest extends TestCase
{
    /** @var PaymentMethodRepositoryInterface<PaymentMethodInterface>&MockObject */
    private PaymentMethodRepositoryInterface&MockObject $paymentMethodRepository;

    private WebhookRequestVerifierInterface&MockObject $webhookRequestVerifier;

    private VaultingCapabilityWebhookProcessorInterface&MockObject $vaultingCapabilityWebhookProcessor;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $this->webhookRequestVerifier = $this->createMock(WebhookRequestVerifierInterface::class);
        $this->vaultingCapabilityWebhookProcessor = $this->createMock(VaultingCapabilityWebhookProcessorInterface::class);
    }

    public function test_it_returns_the_response_of_the_decorated_action(): void
    {
        $response = new Response('', Response::HTTP_NO_CONTENT);
        $action = $this->action(static fn (Request $request, string $code): Response => 'PAYPAL' === $code ? $response : new Response('', Response::HTTP_INTERNAL_SERVER_ERROR));

        self::assertSame($response, $action(new Request(), 'PAYPAL'));
    }

    public function test_it_answers_not_found_to_a_webhook_whose_signature_it_could_not_verify(): void
    {
        $action = $this->action(static fn (): Response => throw new PayPalWebhookSignatureNotVerifiedException('PAYPAL'));

        $response = $action(new Request(), 'PAYPAL');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('{"error":"Not found"}', $response->getContent());
    }

    public function test_it_accepts_a_webhook_event_it_ignores_so_paypal_does_not_deliver_it_again(): void
    {
        $action = $this->action(static fn (): Response => throw new PayPalWebhookEventIgnoredException('ignored'));

        self::assertSame(Response::HTTP_NO_CONTENT, $action(new Request(), 'PAYPAL')->getStatusCode());
    }

    public function test_it_lets_any_other_failure_through(): void
    {
        $action = $this->action(static fn (): Response => throw new \RuntimeException('database is down'));

        $this->expectExceptionMessage('database is down');

        $action(new Request(), 'PAYPAL');
    }

    public function test_it_refreshes_the_vaulting_capability_of_the_paypal_method_the_signed_event_was_sent_to(): void
    {
        $paymentMethod = $this->paymentMethod('sylius_paypal');
        $request = $this->capabilityUpdatedRequest();
        $this->paymentMethodRepository->method('findOneBy')->with(['code' => 'PAYPAL'])->willReturn($paymentMethod);
        $this->webhookRequestVerifier->method('verify')->with($request, $paymentMethod)->willReturn(true);

        $this->vaultingCapabilityWebhookProcessor->expects(self::once())->method('process')->with(['merchant_id' => 'MERCHANT_ID'], $paymentMethod);

        $response = $this->action(static fn (): Response => throw new \LogicException('Sylius must not see the event.'))($request, 'PAYPAL');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function test_it_answers_not_found_to_a_capability_event_whose_signature_it_could_not_verify(): void
    {
        $this->paymentMethodRepository->method('findOneBy')->willReturn($this->paymentMethod('sylius_paypal'));
        $this->webhookRequestVerifier->method('verify')->willReturn(false);

        $this->vaultingCapabilityWebhookProcessor->expects(self::never())->method('process');

        $response = $this->action(static fn (): Response => new Response())($this->capabilityUpdatedRequest(), 'PAYPAL');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function test_it_leaves_a_capability_event_sent_to_another_gateway_to_sylius(): void
    {
        $response = new Response('', Response::HTTP_NO_CONTENT);
        $this->paymentMethodRepository->method('findOneBy')->willReturn($this->paymentMethod('stripe'));

        $this->webhookRequestVerifier->expects(self::never())->method('verify');
        $this->vaultingCapabilityWebhookProcessor->expects(self::never())->method('process');

        self::assertSame($response, $this->action(static fn (): Response => $response)($this->capabilityUpdatedRequest(), 'STRIPE'));
    }

    public function test_it_leaves_every_other_event_to_sylius(): void
    {
        $response = new Response('', Response::HTTP_NO_CONTENT);

        $this->paymentMethodRepository->expects(self::never())->method('findOneBy');

        $request = new Request(content: (string) json_encode(['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => ['id' => 'CAPTURE_ID']]));

        self::assertSame($response, $this->action(static fn (): Response => $response)($request, 'PAYPAL'));
    }

    /** @param callable(Request, string): Response $decoratedAction */
    private function action(callable $decoratedAction): PaymentMethodNotifyAction
    {
        return new PaymentMethodNotifyAction(
            $decoratedAction,
            new NullLogger(),
            $this->paymentMethodRepository,
            $this->webhookRequestVerifier,
            $this->vaultingCapabilityWebhookProcessor,
        );
    }

    private function capabilityUpdatedRequest(): Request
    {
        return new Request(content: (string) json_encode([
            'id' => 'WH-1',
            'event_type' => 'CUSTOMER.MERCHANT-INTEGRATION.CAPABILITY-UPDATED',
            'resource' => ['merchant_id' => 'MERCHANT_ID'],
        ]));
    }

    private function paymentMethod(string $factoryName): PaymentMethod
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName($factoryName);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        return $paymentMethod;
    }
}
