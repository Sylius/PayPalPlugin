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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\Controller\UpdatePermissionsAction;
use Sylius\PayPalPlugin\Exception\OnboardingAccountMismatchException;
use Sylius\PayPalPlugin\Exception\OnboardingFailedException;
use Sylius\PayPalPlugin\Exception\OnboardingSessionExpiredException;
use Sylius\PayPalPlugin\Model\Capability;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\PermissionsUpdateResult;
use Sylius\PayPalPlugin\Model\PermissionsVerification;
use Sylius\PayPalPlugin\Onboarding\Processor\PermissionsUpdateProcessorInterface;
use Sylius\PayPalPlugin\Provider\HostProvider;
use Sylius\PayPalPlugin\Provider\OnboardingStatusMessagesProvider;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class UpdatePermissionsActionTest extends TestCase
{
    private const UPDATE_URL = 'http://admin/payment-methods/7/edit';

    private PaymentMethodRepositoryInterface&MockObject $paymentMethodRepository;

    private PermissionsUpdateProcessorInterface&MockObject $permissionsUpdateProcessor;

    private LoggerInterface&MockObject $logger;

    private PaymentMethodInterface&MockObject $paymentMethod;

    private UpdatePermissionsAction $action;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $this->permissionsUpdateProcessor = $this->createMock(PermissionsUpdateProcessorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $name, array $parameters = []): string => 'http://admin/payment-methods/' . $parameters['id'] . '/edit',
        );

        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius_paypal');
        $gatewayConfig->setConfig(['sandbox' => true]);
        $this->paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->paymentMethod->method('getId')->willReturn(7);
        $this->paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $this->action = new UpdatePermissionsAction(
            $this->paymentMethodRepository,
            $this->permissionsUpdateProcessor,
            new OnboardingStatusMessagesProvider(new HostProvider()),
            $urlGenerator,
            $this->logger,
        );
    }

    public function test_it_updates_the_permissions_and_returns_to_the_payment_method(): void
    {
        $this->paymentMethodRepository->method('find')->with(7)->willReturn($this->paymentMethod);
        $this->permissionsUpdateProcessor
            ->expects(self::once())
            ->method('process')
            ->with($this->paymentMethod, 'AUTH_CODE', 'SHARED_ID')
            ->willReturn($this->updateResult(PermissionsVerification::upToDate()))
        ;
        $request = $this->request(['authCode' => 'AUTH_CODE', 'sharedId' => 'SHARED_ID']);

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(['redirectUrl' => self::UPDATE_URL, 'completed' => true], $this->content($response));
        self::assertSame(['success' => ['sylius_paypal.permissions.update.completed']], $this->flashBag($request)->all());
    }

    public function test_it_tells_which_capabilities_paypal_has_not_activated_yet(): void
    {
        $this->paymentMethodRepository->method('find')->willReturn($this->paymentMethod);
        $this->permissionsUpdateProcessor->method('process')->willReturn($this->updateResult(PermissionsVerification::awaitingPayPal([Capability::APPLE_PAY => 'PENDING', Capability::GOOGLE_PAY => 'NEED_DATA'])));
        $request = $this->request(['authCode' => 'AUTH_CODE', 'sharedId' => 'SHARED_ID']);

        $response = ($this->action)($request);

        self::assertSame(['redirectUrl' => self::UPDATE_URL, 'completed' => false], $this->content($response));
        self::assertSame(['warning' => [[
            'message' => 'sylius_paypal.permissions.update.awaiting_paypal',
            'parameters' => ['%capabilities%' => 'APPLE_PAY: PENDING, GOOGLE_PAY: NEED_DATA'],
        ]]], $this->flashBag($request)->all());
    }

    public function test_it_tells_which_capabilities_paypal_has_not_granted(): void
    {
        $this->paymentMethodRepository->method('find')->willReturn($this->paymentMethod);
        $this->permissionsUpdateProcessor->method('process')->willReturn($this->updateResult(PermissionsVerification::consentRequired([Capability::GOOGLE_PAY])));
        $request = $this->request(['authCode' => 'AUTH_CODE', 'sharedId' => 'SHARED_ID']);

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(['redirectUrl' => self::UPDATE_URL, 'completed' => false], $this->content($response));
        self::assertSame(['warning' => [[
            'message' => 'sylius_paypal.permissions.update.not_granted',
            'parameters' => ['%capabilities%' => Capability::GOOGLE_PAY],
        ]]], $this->flashBag($request)->all());
    }

    public function test_it_warns_about_what_the_paypal_account_still_lacks(): void
    {
        $this->paymentMethodRepository->method('find')->willReturn($this->paymentMethod);
        $this->permissionsUpdateProcessor->method('process')->willReturn(new PermissionsUpdateResult(new OnboardingStatus(false, true), PermissionsVerification::upToDate()));
        $request = $this->request(['authCode' => 'AUTH_CODE', 'sharedId' => 'SHARED_ID']);

        ($this->action)($request);

        self::assertNotEmpty($this->flashBag($request)->peek('warning'));
    }

    public function test_it_refuses_another_paypal_account(): void
    {
        $this->paymentMethodRepository->method('find')->willReturn($this->paymentMethod);
        $this->permissionsUpdateProcessor->method('process')->willThrowException(new OnboardingAccountMismatchException('MERCHANT_ID', 'ANOTHER_MERCHANT_ID'));
        $request = $this->request(['authCode' => 'AUTH_CODE', 'sharedId' => 'SHARED_ID']);

        $this->logger->expects(self::once())->method('error');

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame(['redirectUrl' => self::UPDATE_URL], $this->content($response));
        self::assertSame(['error' => ['sylius_paypal.permissions.update.another_account']], $this->flashBag($request)->all());
    }

    public function test_it_reports_an_update_that_failed(): void
    {
        $this->paymentMethodRepository->method('find')->willReturn($this->paymentMethod);
        $this->permissionsUpdateProcessor->method('process')->willThrowException(new OnboardingFailedException(new \RuntimeException('PayPal is down')));
        $request = $this->request(['authCode' => 'AUTH_CODE', 'sharedId' => 'SHARED_ID']);

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame(['error' => ['sylius_paypal.permissions.update.failed']], $this->flashBag($request)->all());
    }

    public function test_it_reports_an_expired_onboarding_session(): void
    {
        $this->paymentMethodRepository->method('find')->willReturn($this->paymentMethod);
        $this->permissionsUpdateProcessor->method('process')->willThrowException(new OnboardingSessionExpiredException());
        $request = $this->request(['authCode' => 'AUTH_CODE', 'sharedId' => 'SHARED_ID']);

        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame(['error' => ['sylius_paypal.onboarding_session_expired']], $this->flashBag($request)->all());
    }

    public function test_it_rejects_a_request_without_the_paypal_codes(): void
    {
        $this->paymentMethodRepository->method('find')->willReturn($this->paymentMethod);

        $this->permissionsUpdateProcessor->expects(self::never())->method('process');

        $response = ($this->action)($this->request(['authCode' => 'AUTH_CODE']));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame(['redirectUrl' => self::UPDATE_URL], $this->content($response));
    }

    public function test_it_does_not_find_a_payment_method_that_is_not_paypal(): void
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('offline');
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $this->paymentMethodRepository->method('find')->willReturn($paymentMethod);

        $this->permissionsUpdateProcessor->expects(self::never())->method('process');

        $this->expectException(NotFoundHttpException::class);

        ($this->action)($this->request(['authCode' => 'AUTH_CODE', 'sharedId' => 'SHARED_ID']));
    }

    public function test_it_does_not_find_a_missing_payment_method(): void
    {
        $this->paymentMethodRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        ($this->action)($this->request(['authCode' => 'AUTH_CODE', 'sharedId' => 'SHARED_ID']));
    }

    private function updateResult(PermissionsVerification $verification): PermissionsUpdateResult
    {
        return new PermissionsUpdateResult(new OnboardingStatus(true, true), $verification);
    }

    /** @param array<string, mixed> $body */
    private function request(array $body): Request
    {
        $request = Request::create(
            '/payment-methods/7/paypal-permissions',
            'POST',
            content: (string) json_encode($body),
            server: ['CONTENT_TYPE' => 'application/json'],
        );
        $request->attributes->set('id', 7);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    private function flashBag(Request $request): FlashBagInterface
    {
        /** @var FlashBagInterface $flashBag */
        $flashBag = $request->getSession()->getBag('flashes');

        return $flashBag;
    }

    /** @return array<string, mixed> */
    private function content(JsonResponse $response): array
    {
        return (array) json_decode((string) $response->getContent(), true);
    }
}
