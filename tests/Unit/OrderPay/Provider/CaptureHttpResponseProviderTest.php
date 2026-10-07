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

namespace Tests\Sylius\PayPalPlugin\Unit\OrderPay\Provider;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\OrderPay\Provider\CaptureHttpResponseProvider;
use Sylius\PayPalPlugin\Provider\PayPalPaymentPageContextProviderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class CaptureHttpResponseProviderTest extends TestCase
{
    private Environment&MockObject $twig;

    private PayPalPaymentPageContextProviderInterface&MockObject $contextProvider;

    private UrlGeneratorInterface&MockObject $router;

    private RequestConfiguration&MockObject $requestConfiguration;

    private Session $session;

    private CaptureHttpResponseProvider $provider;

    protected function setUp(): void
    {
        $this->twig = $this->createMock(Environment::class);
        $this->contextProvider = $this->createMock(PayPalPaymentPageContextProviderInterface::class);
        $this->router = $this->createMock(UrlGeneratorInterface::class);
        $this->requestConfiguration = $this->createMock(RequestConfiguration::class);

        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setLocale('en_US');
        $request->setSession($this->session);
        $this->requestConfiguration->method('getRequest')->willReturn($request);

        $this->provider = new CaptureHttpResponseProvider($this->twig, $this->contextProvider, $this->router, 'https://www.sandbox.paypal.com');
    }

    /** @return iterable<string, array{string, bool}> */
    public static function states(): iterable
    {
        yield 'waiting for the payer' => [PaymentRequestInterface::STATE_NEW, true];
        yield 'abandoned' => [PaymentRequestInterface::STATE_CANCELLED, true];
        yield 'waiting for capture-end' => [PaymentRequestInterface::STATE_PROCESSING, false];
        yield 'completed' => [PaymentRequestInterface::STATE_COMPLETED, false];
        yield 'failed' => [PaymentRequestInterface::STATE_FAILED, false];
    }

    #[DataProvider('states')]
    public function test_it_answers_only_a_payment_request_the_payer_still_has_to_act_on(string $state, bool $supported): void
    {
        self::assertSame($supported, $this->provider->supports($this->requestConfiguration, $this->paymentRequestIn($state)));
    }

    public function test_it_renders_the_payment_page_that_creates_paypal_orders_for_the_payment_request(): void
    {
        $paymentRequest = $this->paymentRequestIn(PaymentRequestInterface::STATE_NEW);

        $this->contextProvider->method('provide')->with($paymentRequest->getPayment(), 'en_US')->willReturn(['amount' => '20.00']);
        $this->router->method('generate')->with('sylius_paypal_shop_create_paypal_order_for_payment_request', ['hash' => 'PAYMENT_REQUEST_HASH'])->willReturn('/en_US/paypal/payment-requests/PAYMENT_REQUEST_HASH/order');
        $this->twig->expects(self::once())->method('render')->with('@SyliusPayPalPlugin/pay_with_paypal.html.twig', [
            'amount' => '20.00',
            'createPayPalOrderUrl' => '/en_US/paypal/payment-requests/PAYMENT_REQUEST_HASH/order',
        ])->willReturn('PAGE');

        $response = $this->provider->getResponse($this->requestConfiguration, $paymentRequest);

        self::assertSame('PAGE', $response->getContent());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame(
            'publickey-credentials-get=(self "https://www.sandbox.paypal.com"), publickey-credentials-create=(self "https://www.sandbox.paypal.com")',
            $response->headers->get('Permissions-Policy'),
        );
    }

    public function test_it_renders_the_payment_page_for_the_plain_request_sylius_2_3_hands_over(): void
    {
        $paymentRequest = $this->paymentRequestIn(PaymentRequestInterface::STATE_NEW);
        $request = new Request();
        $request->setLocale('pl_PL');

        $this->contextProvider->expects(self::once())->method('provide')->with($paymentRequest->getPayment(), 'pl_PL')->willReturn([]);
        $this->twig->method('render')->willReturn('PAGE');

        self::assertTrue($this->provider->supports($request, $paymentRequest));
        self::assertSame('PAGE', $this->provider->getResponse($request, $paymentRequest)->getContent());
    }

    public function test_it_answers_a_failed_payment_request_only_when_3d_secure_refused_the_card(): void
    {
        self::assertTrue($this->provider->supports($this->requestConfiguration, $this->paymentRequestIn(PaymentRequestInterface::STATE_FAILED, ['three_d_secure' => 'declined'])));
        self::assertFalse($this->provider->supports($this->requestConfiguration, $this->paymentRequestIn(PaymentRequestInterface::STATE_FAILED, ['reason' => 'PayPal did not capture the order.'])));
    }

    public function test_it_asks_the_payer_to_authenticate_the_card_again(): void
    {
        $this->router->method('generate')->with('sylius_shop_order_pay', ['tokenValue' => 'TOKEN'])->willReturn('/en_US/order/TOKEN/pay');

        $response = $this->provider->getResponse($this->requestConfiguration, $this->paymentRequestIn(PaymentRequestInterface::STATE_CANCELLED, ['three_d_secure' => 'retry']));

        self::assertSame('/en_US/order/TOKEN/pay', $response->headers->get('Location'));
        self::assertSame(['sylius_paypal.three_d_secure_retry'], $this->session->getFlashBag()->peek('error'));
    }

    public function test_it_sends_the_payer_of_a_refused_card_to_the_order(): void
    {
        $this->router->method('generate')->with('sylius_shop_order_show', ['tokenValue' => 'TOKEN'])->willReturn('/en_US/order/TOKEN');

        $response = $this->provider->getResponse($this->requestConfiguration, $this->paymentRequestIn(PaymentRequestInterface::STATE_FAILED, ['three_d_secure' => 'declined']));

        self::assertSame('/en_US/order/TOKEN', $response->headers->get('Location'));
        self::assertSame(['sylius_paypal.three_d_secure_declined'], $this->session->getFlashBag()->peek('error'));
    }

    public function test_it_tells_the_payer_who_cancelled_at_the_bank_that_the_payment_was_cancelled(): void
    {
        $this->router->method('generate')->willReturn('/en_US/order/TOKEN/pay');
        $this->requestConfiguration->getRequest()->query->set('payer_cancelled', '1');
        $this->requestConfiguration->getRequest()->query->set('errorcode', 'payment_error');

        $this->provider->getResponse($this->requestConfiguration, $this->paymentRequestIn(PaymentRequestInterface::STATE_CANCELLED));

        self::assertSame(['sylius_paypal.payment_cancelled'], $this->session->getFlashBag()->peek('info'));
    }

    public function test_it_tells_the_payer_the_bank_refused_that_something_went_wrong(): void
    {
        $this->router->method('generate')->willReturn('/en_US/order/TOKEN/pay');
        $this->requestConfiguration->getRequest()->query->set('payer_cancelled', '1');
        $this->requestConfiguration->getRequest()->query->set('errorcode', 'processing_error');

        $this->provider->getResponse($this->requestConfiguration, $this->paymentRequestIn(PaymentRequestInterface::STATE_CANCELLED));

        self::assertSame(['sylius_paypal.something_went_wrong'], $this->session->getFlashBag()->peek('error'));
    }

    public function test_it_thanks_the_payer_of_a_payment_the_bank_has_not_settled_yet(): void
    {
        $paymentRequest = $this->paymentRequestIn(PaymentRequestInterface::STATE_COMPLETED, paymentState: 'processing');
        $this->router->method('generate')->with('sylius_shop_order_thank_you')->willReturn('/en_US/order/thank-you');

        self::assertTrue($this->provider->supports($this->requestConfiguration, $paymentRequest));
        self::assertSame('/en_US/order/thank-you', $this->provider->getResponse($this->requestConfiguration, $paymentRequest)->headers->get('Location'));
        self::assertSame(['sylius_paypal.payment_pending'], $this->session->getFlashBag()->peek('info'));
    }

    public function test_it_leaves_a_completed_payment_to_the_after_pay_page(): void
    {
        self::assertFalse($this->provider->supports($this->requestConfiguration, $this->paymentRequestIn(PaymentRequestInterface::STATE_COMPLETED, paymentState: 'completed')));
    }

    public function test_it_sends_the_payer_of_an_abandoned_attempt_back_to_pay_the_order(): void
    {
        $this->router->method('generate')->with('sylius_shop_order_pay', ['tokenValue' => 'TOKEN'])->willReturn('/en_US/order/TOKEN/pay');
        $this->twig->expects(self::never())->method('render');

        $response = $this->provider->getResponse($this->requestConfiguration, $this->paymentRequestIn(PaymentRequestInterface::STATE_CANCELLED));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/en_US/order/TOKEN/pay', $response->getTargetUrl());
        self::assertSame([], $this->session->getFlashBag()->peek('error'));
    }

    /** @param array<string, mixed> $responseData */
    private function paymentRequestIn(string $state, array $responseData = [], string $paymentState = 'new'): PaymentRequestInterface&MockObject
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getTokenValue')->willReturn('TOKEN');
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getState')->willReturn($paymentState);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getId')->willReturn('PAYMENT_REQUEST_HASH');
        $paymentRequest->method('getState')->willReturn($state);
        $paymentRequest->method('getResponseData')->willReturn($responseData);
        $paymentRequest->method('getPayment')->willReturn($payment);

        return $paymentRequest;
    }
}
