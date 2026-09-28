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
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\PayPalPlugin\Controller\PayWithPayPalFormAction;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Provider\PayPalPaymentPageContextProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class PayWithPayPalFormActionTest extends TestCase
{
    private const RENDERED = 'RENDERED';

    private Environment&MockObject $twig;

    private PaymentRepositoryInterface&Stub $paymentRepository;

    private PayPalPaymentPageContextProviderInterface&MockObject $contextProvider;

    private PayWithPayPalFormAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->twig = $this->createMock(Environment::class);
        $this->paymentRepository = $this->createStub(PaymentRepositoryInterface::class);
        $this->contextProvider = $this->createMock(PayPalPaymentPageContextProviderInterface::class);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route): string => $route);

        $this->action = new PayWithPayPalFormAction(
            twig: $this->twig,
            paymentRepository: $this->paymentRepository,
            contextProvider: $this->contextProvider,
            router: $router,
        );
    }

    public function test_it_renders_the_page_with_the_context_it_is_given(): void
    {
        $payment = $this->payment(PaymentInterface::STATE_NEW);

        $this->contextProvider
            ->expects(self::once())
            ->method('provide')
            ->with($payment, 'en_US')
            ->willReturn(['order' => 'ORDER'])
        ;
        $this->twig
            ->expects(self::once())
            ->method('render')
            ->with('@SyliusPayPalPlugin/pay_with_paypal.html.twig', ['order' => 'ORDER'])
            ->willReturn(self::RENDERED)
        ;

        $response = ($this->action)($this->request());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(self::RENDERED, $response->getContent());
    }

    public function test_it_sends_the_buyer_to_the_thank_you_page_when_the_payment_is_already_completed(): void
    {
        $this->payment(PaymentInterface::STATE_COMPLETED);

        $this->twig->expects(self::never())->method('render');
        $this->contextProvider->expects(self::never())->method('provide');

        $response = ($this->action)($this->request());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('sylius_shop_order_thank_you', $response->getTargetUrl());
    }

    public function test_it_answers_with_not_found_when_the_payment_does_not_exist(): void
    {
        $this->paymentRepository->method('findOneByOrderToken')->willReturn(null);

        $this->twig->expects(self::never())->method('render');
        $this->expectException(NotFoundHttpException::class);

        ($this->action)($this->request());
    }

    public function test_it_answers_with_not_found_when_the_payment_is_not_for_paypal(): void
    {
        $this->payment(PaymentInterface::STATE_NEW, 'offline');

        $this->twig->expects(self::never())->method('render');
        $this->contextProvider->expects(self::never())->method('provide');
        $this->expectException(NotFoundHttpException::class);

        ($this->action)($this->request());
    }

    public function test_it_forbids_storing_the_rendered_page(): void
    {
        $this->payment(PaymentInterface::STATE_NEW);
        $this->twig->method('render')->willReturn(self::RENDERED);

        $response = ($this->action)($this->request());

        self::assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
    }

    public function test_it_delegates_web_authn_permissions_to_both_pay_pal_origins_without_a_configured_web_url(): void
    {
        $this->payment(PaymentInterface::STATE_NEW);
        $this->twig->method('render')->willReturn(self::RENDERED);

        $response = ($this->action)($this->request());

        $permissionsPolicy = (string) $response->headers->get('Permissions-Policy');
        self::assertStringContainsString('publickey-credentials-get=(self "https://www.paypal.com" "https://www.sandbox.paypal.com")', $permissionsPolicy);
        self::assertStringContainsString('publickey-credentials-create=(self "https://www.paypal.com" "https://www.sandbox.paypal.com")', $permissionsPolicy);
    }

    public function test_it_delegates_web_authn_permissions_to_only_the_configured_web_url(): void
    {
        $action = new PayWithPayPalFormAction(
            twig: $this->twig,
            paymentRepository: $this->paymentRepository,
            contextProvider: $this->contextProvider,
            router: $this->createStub(UrlGeneratorInterface::class),
            webUrl: 'https://www.sandbox.paypal.com',
        );
        $this->payment(PaymentInterface::STATE_NEW);
        $this->twig->method('render')->willReturn(self::RENDERED);

        $response = $action($this->request());

        self::assertSame(
            'publickey-credentials-get=(self "https://www.sandbox.paypal.com"), publickey-credentials-create=(self "https://www.sandbox.paypal.com")',
            $response->headers->get('Permissions-Policy'),
        );
    }

    private function payment(string $state, string $factoryName = SyliusPayPalExtension::PAYPAL_FACTORY_NAME): PaymentInterface&Stub
    {
        $gatewayConfig = $this->createStub(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);

        $paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createStub(PaymentInterface::class);
        $payment->method('getState')->willReturn($state);
        $payment->method('getMethod')->willReturn($paymentMethod);

        $this->paymentRepository->method('findOneByOrderToken')->willReturn($payment);

        return $payment;
    }

    private function request(): Request
    {
        $request = new Request(attributes: ['orderToken' => 'ORDER_TOKEN', 'paymentId' => '1']);
        $request->setLocale('en_US');

        return $request;
    }
}
