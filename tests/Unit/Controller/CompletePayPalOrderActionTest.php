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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Controller\CompletePayPalOrderAction;
use Sylius\PayPalPlugin\Exception\ThreeDSecureAuthenticationFailedException;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Verifier\ThreeDSecureVerifierInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CompletePayPalOrderActionTest extends TestCase
{
    private PaymentStateManagerInterface&MockObject $paymentStateManager;

    private OrderProviderInterface&MockObject $orderProvider;

    private CacheAuthorizeClientApiInterface&MockObject $authorizeClientApi;

    private OrderDetailsApiInterface&MockObject $orderDetailsApi;

    private ThreeDSecureVerifierInterface&MockObject $threeDSecureVerifier;

    private FlashBagInterface&MockObject $flashBag;

    private OrderInterface&MockObject $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentStateManager = $this->createMock(PaymentStateManagerInterface::class);
        $this->orderProvider = $this->createMock(OrderProviderInterface::class);
        $this->authorizeClientApi = $this->createMock(CacheAuthorizeClientApiInterface::class);
        $this->orderDetailsApi = $this->createMock(OrderDetailsApiInterface::class);
        $this->threeDSecureVerifier = $this->createMock(ThreeDSecureVerifierInterface::class);
        $this->flashBag = $this->createMock(FlashBagInterface::class);

        $this->authorizeClientApi->method('authorize')->willReturn('TOKEN');

        $this->order = $this->createMock(OrderInterface::class);
        $this->order->method('getTokenValue')->willReturn('ORDER_TOKEN');
        $this->orderProvider->method('provideOrderByToken')->with('ORDER_TOKEN')->willReturn($this->order);
    }

    public function test_it_completes_the_payment_when_the_authentication_result_is_accepted(): void
    {
        $payment = $this->payment();
        $this->payments(processing: $payment, new: null);

        $this->orderDetailsApi->expects(self::once())->method('get')->with('TOKEN', 'PAYPAL_ORDER_ID')->willReturn(['id' => 'PAYPAL_ORDER_ID']);
        $this->threeDSecureVerifier->expects(self::once())->method('verify')->with(['id' => 'PAYPAL_ORDER_ID']);
        $this->paymentStateManager->expects(self::once())->method('complete')->with($payment);

        self::assertSame(Response::HTTP_OK, $this->action()($this->request())->getStatusCode());
    }

    public function test_it_answers_with_the_paypal_order_id_under_both_the_new_and_the_legacy_key(): void
    {
        $this->payments(processing: $this->payment(), new: null);

        $content = json_decode((string) $this->action()($this->request())->getContent(), true);

        self::assertSame('PAYPAL_ORDER_ID', $content['orderId']);
        self::assertSame('PAYPAL_ORDER_ID', $content['orderID']);
        self::assertSame('THANK_YOU_URL', $content['return_url']);
    }

    public function test_it_cancels_the_payment_when_the_authentication_was_declined(): void
    {
        $payment = $this->payment();
        $this->payments(processing: $payment, new: null);
        $this->threeDSecureVerifier->method('verify')->willThrowException(new ThreeDSecureAuthenticationFailedException(retryable: false));

        $this->paymentStateManager->expects(self::once())->method('cancel')->with($payment);
        $this->paymentStateManager->expects(self::never())->method('complete');

        $this->action()($this->request());
    }

    public function test_it_sends_the_buyer_back_to_the_order_when_the_authentication_was_declined(): void
    {
        $this->payments(processing: $this->payment(), new: null);
        $this->threeDSecureVerifier->method('verify')->willThrowException(new ThreeDSecureAuthenticationFailedException(retryable: false));

        $content = json_decode((string) $this->action()($this->request())->getContent(), true);

        self::assertSame('ORDER_SHOW_URL', $content['return_url']);
    }

    public function test_it_sends_the_buyer_back_to_the_payment_page_when_the_authentication_can_be_retried(): void
    {
        $this->payments(processing: $this->payment(), new: $this->payment(id: 42));
        $this->threeDSecureVerifier->method('verify')->willThrowException(new ThreeDSecureAuthenticationFailedException(retryable: true));

        $content = json_decode((string) $this->action()($this->request())->getContent(), true);

        self::assertSame('PAY_PAGE_URL', $content['return_url']);
    }

    public function test_it_flashes_an_error_when_the_authentication_was_not_accepted(): void
    {
        $this->payments(processing: $this->payment(), new: null);
        $this->threeDSecureVerifier->method('verify')->willThrowException(new ThreeDSecureAuthenticationFailedException(retryable: false));

        $this->flashBag->expects(self::once())->method('add')->with('error', 'sylius_paypal.three_d_secure_declined');

        $this->action()($this->request());
    }

    public function test_it_does_not_send_the_buyer_to_the_thank_you_page_when_the_capture_was_refused(): void
    {
        $payment = $this->payment(state: PaymentInterface::STATE_PROCESSING);
        $this->payments(processing: $payment, new: $this->payment(id: 42));

        $this->paymentStateManager->expects(self::once())->method('cancel')->with($payment);

        $content = json_decode((string) $this->action()($this->request())->getContent(), true);

        self::assertSame('PAY_PAGE_URL', $content['return_url']);
    }

    public function test_it_answers_with_a_conflict_when_no_payment_is_being_processed(): void
    {
        $this->payments(processing: null, new: null);

        $this->paymentStateManager->expects(self::never())->method('complete');

        self::assertSame(Response::HTTP_CONFLICT, $this->action()($this->request())->getStatusCode());
    }

    public function test_it_refuses_a_paypal_order_id_that_does_not_match_the_payment(): void
    {
        $this->payments(processing: $this->payment(), new: null);

        $this->paymentStateManager->expects(self::never())->method('complete');

        $response = $this->action()($this->request('ANOTHER_PAYPAL_ORDER_ID'));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function test_it_verifies_the_authentication_when_the_request_identifies_the_matching_paypal_order(): void
    {
        $payment = $this->payment();
        $this->payments(processing: $payment, new: null);

        $this->paymentStateManager->expects(self::once())->method('complete')->with($payment);

        self::assertSame(Response::HTTP_OK, $this->action()($this->request('PAYPAL_ORDER_ID'))->getStatusCode());
    }

    public function test_it_completes_the_payment_without_verifying_when_the_verifier_is_not_wired(): void
    {
        $payment = $this->payment();
        $this->payments(processing: $payment, new: null);

        $this->orderDetailsApi->expects(self::never())->method('get');
        $this->paymentStateManager->expects(self::once())->method('complete')->with($payment);

        self::assertSame(Response::HTTP_OK, $this->actionWithoutVerifier()($this->request())->getStatusCode());
    }

    private function action(): CompletePayPalOrderAction
    {
        return new CompletePayPalOrderAction(
            $this->paymentStateManager,
            $this->router(),
            $this->orderProvider,
            $this->authorizeClientApi,
            $this->orderDetailsApi,
            $this->threeDSecureVerifier,
        );
    }

    private function actionWithoutVerifier(): CompletePayPalOrderAction
    {
        return new CompletePayPalOrderAction($this->paymentStateManager, $this->router(), $this->orderProvider);
    }

    private function router(): UrlGeneratorInterface&MockObject
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route): string => match ($route) {
            'sylius_shop_order_thank_you' => 'THANK_YOU_URL',
            'sylius_shop_order_show' => 'ORDER_SHOW_URL',
            'sylius_paypal_shop_pay_with_paypal_form' => 'PAY_PAGE_URL',
            default => 'UNKNOWN_URL',
        });

        return $router;
    }

    private function payments(?PaymentInterface $processing, ?PaymentInterface $new): void
    {
        $this->order->method('getLastPayment')->willReturnCallback(
            static fn (?string $state = null): ?PaymentInterface => match ($state) {
                PaymentInterface::STATE_PROCESSING => $processing,
                PaymentInterface::STATE_NEW => $new,
                default => null,
            },
        );
    }

    private function payment(int $id = 1, string $state = PaymentInterface::STATE_COMPLETED): PaymentInterface&MockObject
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getId')->willReturn($id);
        $payment->method('getMethod')->willReturn($this->createMock(PaymentMethodInterface::class));
        $payment->method('getState')->willReturn($state);
        $payment->method('getDetails')->willReturn(['paypal_order_id' => 'PAYPAL_ORDER_ID']);

        return $payment;
    }

    private function request(?string $payPalOrderId = null): Request
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('getBag')->with('flashes')->willReturn($this->flashBag);

        $request = new Request(
            attributes: ['token' => 'ORDER_TOKEN'],
            server: ['CONTENT_TYPE' => 'application/json'],
            content: null === $payPalOrderId ? '' : json_encode(['payPalOrderId' => $payPalOrderId]),
        );
        $request->setSession($session);

        return $request;
    }
}
