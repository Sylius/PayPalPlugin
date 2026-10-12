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
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\PayPalPlugin\Controller\RefundPayPalLateCaptureAction;
use Sylius\PayPalPlugin\Exception\PayPalOrderRefundException;
use Sylius\PayPalPlugin\Processor\PaymentRefundProcessorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class RefundPayPalLateCaptureActionTest extends TestCase
{
    /** @var PaymentRepositoryInterface<PaymentInterface>&Stub */
    private PaymentRepositoryInterface&Stub $paymentRepository;

    private PaymentRefundProcessorInterface&MockObject $refundProcessor;

    private CsrfTokenManagerInterface&Stub $csrfTokenManager;

    private RefundPayPalLateCaptureAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentRepository = $this->createStub(PaymentRepositoryInterface::class);
        $this->refundProcessor = $this->createMock(PaymentRefundProcessorInterface::class);
        $this->csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters): string => sprintf('/admin/orders/%s', (string) $parameters['id']),
        );

        $this->action = new RefundPayPalLateCaptureAction(
            $this->paymentRepository,
            $this->refundProcessor,
            $this->csrfTokenManager,
            $router,
        );
    }

    public function test_it_refunds_the_late_capture_and_returns_to_the_order(): void
    {
        $payment = $this->createStub(PaymentInterface::class);
        $this->csrfTokenManager->method('isTokenValid')->willReturnCallback(
            static fn (CsrfToken $token): bool => '7' === $token->getId() && 'TOKEN' === $token->getValue(),
        );
        $this->paymentRepository->method('findOneByOrderId')->willReturnMap([['7', '3', $payment]]);

        $this->refundProcessor->expects(self::once())->method('refund')->with($payment);

        $request = $this->request();
        $response = ($this->action)($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/orders/3', $response->getTargetUrl());
        self::assertSame(['sylius_paypal.late_capture_refunded'], $request->getSession()->getFlashBag()->peek('success'));
    }

    public function test_it_tells_the_admin_when_the_refund_failed(): void
    {
        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);
        $this->paymentRepository->method('findOneByOrderId')->willReturn($this->createStub(PaymentInterface::class));
        $this->refundProcessor->method('refund')->willThrowException(new PayPalOrderRefundException());

        $request = $this->request();
        ($this->action)($request);

        self::assertSame(['sylius_paypal.late_capture_not_refunded'], $request->getSession()->getFlashBag()->peek('error'));
    }

    public function test_it_refuses_a_request_without_a_valid_csrf_token(): void
    {
        $this->csrfTokenManager->method('isTokenValid')->willReturn(false);

        $this->refundProcessor->expects(self::never())->method('refund');

        $this->expectException(AccessDeniedHttpException::class);

        ($this->action)($this->request());
    }

    public function test_it_refuses_a_payment_that_does_not_belong_to_the_order(): void
    {
        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);
        $this->paymentRepository->method('findOneByOrderId')->willReturn(null);

        $this->refundProcessor->expects(self::never())->method('refund');

        $this->expectException(NotFoundHttpException::class);

        ($this->action)($this->request());
    }

    private function request(): Request
    {
        $request = new Request([], ['_csrf_token' => 'TOKEN'], ['id' => '7', 'orderId' => '3']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
