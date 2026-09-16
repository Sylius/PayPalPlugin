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

use Doctrine\Persistence\ObjectManager;
use GuzzleHttp\Exception\TransferException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\PayPalPlugin\Controller\CreatePayPalOrderFromPaymentPageAction;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class CreatePayPalOrderFromPaymentPageActionTest extends TestCase
{
    private StateMachineInterface&Stub $stateMachine;

    private PaymentStateManagerInterface&MockObject $paymentStateManager;

    private OrderProviderInterface&Stub $orderProvider;

    private CapturePaymentResolverInterface&MockObject $capturePaymentResolver;

    private OrderProcessorInterface&MockObject $orderPaymentProcessor;

    private ObjectManager&MockObject $objectManager;

    private OrderInterface&Stub $order;

    private CreatePayPalOrderFromPaymentPageAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = $this->createStub(StateMachineInterface::class);
        $this->paymentStateManager = $this->createMock(PaymentStateManagerInterface::class);
        $this->orderProvider = $this->createStub(OrderProviderInterface::class);
        $this->capturePaymentResolver = $this->createMock(CapturePaymentResolverInterface::class);
        $this->orderPaymentProcessor = $this->createMock(OrderProcessorInterface::class);
        $this->objectManager = $this->createMock(ObjectManager::class);
        $this->order = $this->createStub(OrderInterface::class);

        $this->orderProvider->method('provideOrderById')->with(42)->willReturn($this->order);

        $this->action = new CreatePayPalOrderFromPaymentPageAction(
            $this->stateMachine,
            $this->paymentStateManager,
            $this->orderProvider,
            $this->capturePaymentResolver,
            $this->orderPaymentProcessor,
            $this->objectManager,
        );
    }

    public function test_it_creates_a_paypal_order_for_the_payment_in_the_cart(): void
    {
        $payment = $this->payment(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $this->payments(processing: null, cart: $payment);

        $this->capturePaymentResolver->expects(self::once())->method('resolve')->with($payment);
        $this->paymentStateManager->expects(self::once())->method('create')->with($payment);
        $this->paymentStateManager->expects(self::once())->method('process')->with($payment);

        self::assertSame(Response::HTTP_OK, ($this->action)($this->request())->getStatusCode());
    }

    public function test_it_answers_with_the_paypal_order_id_under_both_the_new_and_the_legacy_key(): void
    {
        $this->payments(processing: null, cart: $this->payment(SyliusPayPalExtension::PAYPAL_FACTORY_NAME));

        $content = json_decode((string) ($this->action)($this->request())->getContent(), true);

        self::assertSame('PAYPAL_ORDER_ID', $content['orderId']);
        self::assertSame('PAYPAL_ORDER_ID', $content['order_id']);
        self::assertSame(PaymentInterface::STATE_CART, $content['status']);
    }

    public function test_it_cancels_a_live_paypal_attempt_before_creating_a_new_one(): void
    {
        $livePayment = $this->payment(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $payment = $this->payment(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $this->payments(processing: $livePayment, cart: null, cartAfterCancel: $payment);

        $this->paymentStateManager->expects(self::once())->method('cancel')->with($livePayment);
        $this->orderPaymentProcessor->expects(self::once())->method('process')->with($this->order);
        $this->objectManager->expects(self::once())->method('flush');
        $this->capturePaymentResolver->expects(self::once())->method('resolve')->with($payment);

        self::assertSame(Response::HTTP_OK, ($this->action)($this->request())->getStatusCode());
    }

    public function test_it_leaves_a_processing_payment_of_another_gateway_alone(): void
    {
        $payment = $this->payment(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $this->payments(processing: $this->payment('offline'), cart: $payment);

        $this->paymentStateManager->expects(self::never())->method('cancel');
        $this->orderPaymentProcessor->expects(self::never())->method('process');
        $this->capturePaymentResolver->expects(self::once())->method('resolve')->with($payment);

        ($this->action)($this->request());
    }

    public function test_it_answers_with_a_conflict_when_the_order_has_no_payment_to_pay_with(): void
    {
        $this->payments(processing: null, cart: null);

        $this->capturePaymentResolver->expects(self::never())->method('resolve');
        $this->paymentStateManager->expects(self::never())->method('create');

        self::assertSame(Response::HTTP_CONFLICT, ($this->action)($this->request())->getStatusCode());
    }

    public function test_it_leaves_the_abandoned_attempt_alone_without_the_cancelling_collaborators(): void
    {
        $action = new CreatePayPalOrderFromPaymentPageAction(
            $this->stateMachine,
            $this->paymentStateManager,
            $this->orderProvider,
            $this->capturePaymentResolver,
        );
        $this->payments(processing: $this->payment(SyliusPayPalExtension::PAYPAL_FACTORY_NAME), cart: null);

        $this->paymentStateManager->expects(self::never())->method('cancel');
        $this->capturePaymentResolver->expects(self::never())->method('resolve');

        self::assertSame(Response::HTTP_CONFLICT, $action($this->request())->getStatusCode());
    }

    public function test_it_answers_with_a_bad_request_when_paypal_is_unreachable(): void
    {
        $this->payments(processing: null, cart: $this->payment(SyliusPayPalExtension::PAYPAL_FACTORY_NAME));
        $this->capturePaymentResolver->method('resolve')->willThrowException(new TransferException());

        $this->paymentStateManager->expects(self::never())->method('create');
        $request = $this->request();

        self::assertSame(Response::HTTP_BAD_REQUEST, ($this->action)($request)->getStatusCode());
        self::assertSame(['sylius_paypal.something_went_wrong'], $request->getSession()->getBag('flashes')->get('error'));
    }

    private function payments(
        ?PaymentInterface $processing,
        ?PaymentInterface $cart,
        ?PaymentInterface $cartAfterCancel = null,
    ): void {
        $cartAfterCancel ??= $cart;
        $attemptEnded = false;

        $this->order->method('getLastPayment')->willReturnCallback(
            static function (?string $state = null) use ($processing, $cart, $cartAfterCancel, &$attemptEnded): ?PaymentInterface {
                if (PaymentInterface::STATE_PROCESSING === $state) {
                    $attemptEnded = null !== $processing;

                    return $processing;
                }

                if (PaymentInterface::STATE_CART === $state) {
                    return $attemptEnded ? $cartAfterCancel : $cart;
                }

                return null;
            },
        );
    }

    private function payment(string $factoryName): PaymentInterface&Stub
    {
        $gatewayConfig = $this->createStub(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);

        $paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createStub(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_CART);
        $payment->method('getDetails')->willReturn(['paypal_order_id' => 'PAYPAL_ORDER_ID']);

        return $payment;
    }

    private function request(): Request
    {
        $request = new Request([], [], ['id' => 42]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
