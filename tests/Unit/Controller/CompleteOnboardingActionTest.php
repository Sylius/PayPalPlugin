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

namespace Tests\Sylius\PayPalPlugin\Unit\Controller;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Controller\CompleteOnboardingAction;
use Sylius\PayPalPlugin\Exception\OnboardingFailedException;
use Sylius\PayPalPlugin\Exception\OnboardingSessionExpiredException;
use Sylius\PayPalPlugin\Exception\PayPalPaymentMethodAlreadyExistsException;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Model\OnboardingCompletionResult;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Onboarding\Processor\OnboardingCompletionProcessorInterface;
use Sylius\PayPalPlugin\Provider\OnboardingStatusMessagesProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CompleteOnboardingActionTest extends TestCase
{
    private OnboardingCompletionProcessorInterface&MockObject $onboardingCompletionProcessor;

    private UrlGeneratorInterface&MockObject $urlGenerator;

    private LoggerInterface&MockObject $logger;

    private CompleteOnboardingAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->onboardingCompletionProcessor = $this->createMock(OnboardingCompletionProcessorInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->urlGenerator->method('generate')->willReturnCallback(
            fn (string $name, array $parameters = []): string => match ($name) {
                'sylius_admin_payment_method_index' => 'http://admin/payment-methods/',
                'sylius_admin_payment_method_update' => 'http://admin/payment-methods/' . $parameters['id'] . '/edit',
            },
        );

        $this->action = new CompleteOnboardingAction(
            $this->onboardingCompletionProcessor,
            new OnboardingStatusMessagesProvider(),
            $this->urlGenerator,
            $this->logger,
        );
    }

    #[Test]
    public function it_returns_the_edit_url_on_success(): void
    {
        $request = $this->requestWithBody(['authCode' => 'AUTH-CODE', 'sharedId' => 'SHARED-ID']);

        $this->onboardingCompletionProcessor
            ->expects(self::once())
            ->method('process')
            ->with('AUTH-CODE', 'SHARED-ID')
            ->willReturn($this->completionResult(new OnboardingStatus(true, true), true));

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(['redirectUrl' => 'http://admin/payment-methods/42/edit'], json_decode((string) $response->getContent(), true));
        self::assertSame([], $this->flashBag($request)->all());
    }

    #[Test]
    public function it_adds_a_warning_for_each_unmet_onboarding_requirement(): void
    {
        $request = $this->requestWithBody(['authCode' => 'AUTH-CODE', 'sharedId' => 'SHARED-ID']);

        $this->onboardingCompletionProcessor
            ->method('process')
            ->willReturn($this->completionResult(new OnboardingStatus(false, false), false));

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(
            ['warning' => [
                'sylius_paypal.seller_onboarding_payments_not_receivable',
                'sylius_paypal.seller_onboarding_primary_email_not_confirmed',
                'sylius_paypal.webhook_url_not_valid',
            ]],
            $this->flashBag($request)->all(),
        );
    }

    #[Test]
    public function it_returns_bad_request_when_a_paypal_payment_method_already_exists(): void
    {
        $request = $this->requestWithBody(['authCode' => 'AUTH-CODE', 'sharedId' => 'SHARED-ID']);

        $this->onboardingCompletionProcessor->method('process')->willThrowException(new PayPalPaymentMethodAlreadyExistsException());

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame(['error' => ['sylius_paypal.more_than_one_seller_not_allowed']], $this->flashBag($request)->all());
    }

    #[Test]
    public function it_returns_bad_request_when_the_onboarding_session_has_expired(): void
    {
        $request = $this->requestWithBody(['authCode' => 'AUTH-CODE', 'sharedId' => 'SHARED-ID']);

        $this->onboardingCompletionProcessor->method('process')->willThrowException(new OnboardingSessionExpiredException());

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame(['error' => ['sylius_paypal.onboarding_session_expired']], $this->flashBag($request)->all());
    }

    #[Test]
    public function it_returns_bad_request_and_logs_when_the_onboarding_fails(): void
    {
        $request = $this->requestWithBody(['authCode' => 'AUTH-CODE', 'sharedId' => 'SHARED-ID']);

        $this->onboardingCompletionProcessor
            ->method('process')
            ->willThrowException(new OnboardingFailedException(new PayPalPluginException('boom')));

        $this->logger->expects(self::once())->method('error');

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame(['error' => ['sylius_paypal.could_not_create_paypal_payment_method']], $this->flashBag($request)->all());
    }

    #[Test]
    public function it_returns_bad_request_when_the_request_body_is_missing_required_fields(): void
    {
        $this->onboardingCompletionProcessor->expects(self::never())->method('process');

        $response = ($this->action)($this->requestWithBody(['authCode' => 'AUTH-CODE']));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function it_returns_bad_request_when_a_required_field_is_not_a_string(): void
    {
        $this->onboardingCompletionProcessor->expects(self::never())->method('process');

        $response = ($this->action)($this->requestWithBody(['authCode' => ['AUTH-CODE'], 'sharedId' => 'SHARED-ID']));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function it_returns_bad_request_when_the_request_body_is_not_valid_json(): void
    {
        $request = Request::create('/onboarding/complete', 'POST', content: '{not-valid-json');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->onboardingCompletionProcessor->expects(self::never())->method('process');

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    private function completionResult(OnboardingStatus $status, bool $webhookUrlValid): OnboardingCompletionResult
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getId')->willReturn(42);

        return new OnboardingCompletionResult($paymentMethod, $status, $webhookUrlValid);
    }

    private function flashBag(Request $request): FlashBagInterface
    {
        /** @var FlashBagInterface $flashBag */
        $flashBag = $request->getSession()->getBag('flashes');

        return $flashBag;
    }

    /** @param array<string, mixed> $body */
    private function requestWithBody(array $body): Request
    {
        $request = Request::create(
            '/onboarding/complete',
            'POST',
            content: (string) json_encode($body),
            server: ['CONTENT_TYPE' => 'application/json'],
        );
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
