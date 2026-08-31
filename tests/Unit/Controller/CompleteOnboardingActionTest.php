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
use Sylius\PayPalPlugin\Creator\PayPalOnboardingPaymentMethodCreatorInterface;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\SellerOnboardingResult;
use Sylius\PayPalPlugin\Onboarding\Resolver\SellerOnboardingResolverInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;
use Sylius\PayPalPlugin\Provider\SellerNonceProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CompleteOnboardingActionTest extends TestCase
{
    private SellerOnboardingResolverInterface&MockObject $sellerOnboardingResolver;

    private PayPalOnboardingPaymentMethodCreatorInterface&MockObject $onboardingPaymentMethodCreator;

    private SellerNonceProviderInterface&MockObject $sellerNonceProvider;

    private PayPalPaymentMethodProviderInterface&MockObject $payPalPaymentMethodProvider;

    private UrlGeneratorInterface&MockObject $urlGenerator;

    private LoggerInterface&MockObject $logger;

    private CompleteOnboardingAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sellerOnboardingResolver = $this->createMock(SellerOnboardingResolverInterface::class);
        $this->onboardingPaymentMethodCreator = $this->createMock(PayPalOnboardingPaymentMethodCreatorInterface::class);
        $this->sellerNonceProvider = $this->createMock(SellerNonceProviderInterface::class);
        $this->payPalPaymentMethodProvider = $this->createMock(PayPalPaymentMethodProviderInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->action = new CompleteOnboardingAction(
            $this->sellerOnboardingResolver,
            $this->onboardingPaymentMethodCreator,
            $this->sellerNonceProvider,
            $this->payPalPaymentMethodProvider,
            $this->urlGenerator,
            $this->logger,
        );
    }

    #[Test]
    public function it_creates_the_payment_method_and_returns_the_edit_url_on_success(): void
    {
        $request = $this->requestWithBody(['authCode' => 'AUTH-CODE', 'sharedId' => 'SHARED-ID']);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $result = new SellerOnboardingResult('CLIENT-ID', 'CLIENT-SECRET', 'MERCHANT-ID', new OnboardingStatus(true, true));

        $this->urlGenerator->method('generate')->willReturnCallback(
            fn (string $name, array $parameters = []): string => match ($name) {
                'sylius_admin_payment_method_index' => 'http://admin/payment-methods/',
                'sylius_admin_payment_method_update' => 'http://admin/payment-methods/' . $parameters['id'] . '/edit',
                default => throw new \LogicException('Unexpected route: ' . $name),
            },
        );

        $this->payPalPaymentMethodProvider
            ->expects(self::once())
            ->method('exists')
            ->willReturn(false);

        $this->sellerNonceProvider->expects(self::once())->method('get')->willReturn('SELLER-NONCE');
        $this->sellerNonceProvider->expects(self::once())->method('remove');

        $this->sellerOnboardingResolver
            ->expects(self::once())
            ->method('resolve')
            ->with('AUTH-CODE', 'SHARED-ID', 'SELLER-NONCE')
            ->willReturn($result);

        $this->onboardingPaymentMethodCreator
            ->expects(self::once())
            ->method('create')
            ->with($result)
            ->willReturn($paymentMethod);

        $paymentMethod->method('isEnabled')->willReturn(true);
        $paymentMethod->method('getId')->willReturn(42);

        $response = ($this->action)($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['redirectUrl' => 'http://admin/payment-methods/42/edit'],
            json_decode((string) $response->getContent(), true),
        );
    }

    #[Test]
    public function it_returns_bad_request_when_a_paypal_payment_method_already_exists(): void
    {
        $request = $this->requestWithBody(['authCode' => 'AUTH-CODE', 'sharedId' => 'SHARED-ID']);

        $this->urlGenerator->method('generate')->with('sylius_admin_payment_method_index')->willReturn('http://admin/payment-methods/');

        $this->payPalPaymentMethodProvider
            ->expects(self::once())
            ->method('exists')
            ->willReturn(true);

        $this->sellerOnboardingResolver->expects(self::never())->method('resolve');

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame(
            ['error' => ['sylius_paypal.more_than_one_seller_not_allowed']],
            $request->getSession()->getFlashBag()->all(),
        );
    }

    #[Test]
    public function it_returns_bad_request_when_the_seller_nonce_is_missing(): void
    {
        $request = $this->requestWithBody(['authCode' => 'AUTH-CODE', 'sharedId' => 'SHARED-ID']);

        $this->urlGenerator->method('generate')->with('sylius_admin_payment_method_index')->willReturn('http://admin/payment-methods/');

        $this->payPalPaymentMethodProvider
            ->expects(self::once())
            ->method('exists')
            ->willReturn(false);

        $this->sellerNonceProvider->expects(self::once())->method('get')->willReturn(null);
        $this->sellerNonceProvider->expects(self::never())->method('remove');
        $this->sellerOnboardingResolver->expects(self::never())->method('resolve');

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function it_returns_bad_request_when_the_resolver_fails(): void
    {
        $request = $this->requestWithBody(['authCode' => 'AUTH-CODE', 'sharedId' => 'SHARED-ID']);

        $this->urlGenerator->method('generate')->with('sylius_admin_payment_method_index')->willReturn('http://admin/payment-methods/');

        $this->payPalPaymentMethodProvider
            ->expects(self::once())
            ->method('exists')
            ->willReturn(false);

        $this->sellerNonceProvider->expects(self::once())->method('get')->willReturn('SELLER-NONCE');
        $this->sellerNonceProvider->expects(self::never())->method('remove');

        $this->sellerOnboardingResolver
            ->expects(self::once())
            ->method('resolve')
            ->willThrowException(new \RuntimeException('PayPal API error'));

        $this->onboardingPaymentMethodCreator->expects(self::never())->method('create');

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function it_returns_bad_request_when_the_request_body_is_missing_required_fields(): void
    {
        $request = $this->requestWithBody(['authCode' => 'AUTH-CODE']);

        $this->urlGenerator->method('generate')->with('sylius_admin_payment_method_index')->willReturn('http://admin/payment-methods/');

        $this->payPalPaymentMethodProvider->expects(self::never())->method('exists');

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function it_returns_bad_request_when_the_request_body_is_not_valid_json(): void
    {
        $request = Request::create('/onboarding/complete', 'POST', content: '{not-valid-json');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->urlGenerator->method('generate')->with('sylius_admin_payment_method_index')->willReturn('http://admin/payment-methods/');

        $this->payPalPaymentMethodProvider->expects(self::never())->method('exists');

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /** @param array<string, mixed> $body */
    private function requestWithBody(array $body): Request
    {
        $request = Request::create('/onboarding/complete', 'POST', content: (string) json_encode($body));
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
