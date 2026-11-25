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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\CoreBundle\OrderPay\Provider\UrlProviderInterface;
use Sylius\Bundle\CoreBundle\OrderPay\Resolver\PaymentToPayResolverInterface;
use Sylius\Bundle\PaymentBundle\Provider\DefaultActionProviderInterface;
use Sylius\Bundle\PaymentBundle\Provider\DefaultPayloadProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Factory\PaymentRequestFactoryInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentRequest;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\OrderPay\Provider\CapturePaymentRequestPayResponseProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

final class CapturePaymentRequestPayResponseProviderTest extends TestCase
{
    private PaymentToPayResolverInterface&MockObject $paymentToPayResolver;

    /** @var PaymentRequestRepositoryInterface<PaymentRequestInterface>&MockObject */
    private PaymentRequestRepositoryInterface&MockObject $paymentRequestRepository;

    /** @var PaymentRequestFactoryInterface<PaymentRequestInterface>&MockObject */
    private PaymentRequestFactoryInterface&MockObject $paymentRequestFactory;

    private DefaultActionProviderInterface&MockObject $defaultActionProvider;

    private UrlProviderInterface&MockObject $payUrlProvider;

    private OrderInterface&MockObject $order;

    private CapturePaymentRequestPayResponseProvider $provider;

    protected function setUp(): void
    {
        $this->paymentToPayResolver = $this->createMock(PaymentToPayResolverInterface::class);
        $this->paymentRequestRepository = $this->createMock(PaymentRequestRepositoryInterface::class);
        $this->paymentRequestFactory = $this->createMock(PaymentRequestFactoryInterface::class);
        $this->defaultActionProvider = $this->createMock(DefaultActionProviderInterface::class);
        $this->payUrlProvider = $this->createMock(UrlProviderInterface::class);
        $this->order = $this->createMock(OrderInterface::class);

        $defaultPayloadProvider = $this->createStub(DefaultPayloadProviderInterface::class);
        $defaultPayloadProvider->method('getPayload')->willReturn(['default' => 'payload']);

        $this->provider = new CapturePaymentRequestPayResponseProvider(
            $this->paymentToPayResolver,
            $this->paymentRequestRepository,
            $this->paymentRequestFactory,
            $this->defaultActionProvider,
            $defaultPayloadProvider,
            $this->payUrlProvider,
        );
    }

    public function test_it_supports_an_order_paid_with_paypal(): void
    {
        $this->paymentToPayResolver->method('getPayment')->with($this->order)->willReturn($this->paymentWith($this->methodOf(SyliusPayPalExtension::PAYPAL_FACTORY_NAME)));

        self::assertTrue($this->provider->supports(new Request(), $this->order));
    }

    public function test_it_does_not_support_an_order_paid_with_another_gateway(): void
    {
        $this->paymentToPayResolver->method('getPayment')->with($this->order)->willReturn($this->paymentWith($this->methodOf('offline')));

        self::assertFalse($this->provider->supports(new Request(), $this->order));
    }

    public function test_it_does_not_support_an_order_without_a_payment_to_pay(): void
    {
        $this->paymentToPayResolver->method('getPayment')->with($this->order)->willReturn(null);

        self::assertFalse($this->provider->supports(new Request(), $this->order));
    }

    public function test_it_resumes_the_latest_open_attempt_of_the_payment_for_the_default_action(): void
    {
        $paymentMethod = $this->methodOf(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $payment = $this->paymentWith($paymentMethod);
        $this->paymentToPayResolver->method('getPayment')->willReturn($payment);
        $this->paymentRequestFactory->method('create')->with($payment, $paymentMethod)->willReturn(new PaymentRequest($payment, $paymentMethod));
        $this->defaultActionProvider->method('getAction')->willReturn(PaymentRequestInterface::ACTION_CAPTURE);

        $olderAttempt = $this->paymentRequest(PaymentRequestInterface::ACTION_CAPTURE, $paymentMethod, '2026-10-16 10:00:00');
        $latestAttempt = $this->paymentRequest(PaymentRequestInterface::ACTION_CAPTURE, $paymentMethod, '2026-10-16 10:05:00');
        $statusCheck = $this->paymentRequest(PaymentRequestInterface::ACTION_STATUS, $paymentMethod, '2026-10-16 10:10:00');
        $otherMethodAttempt = $this->paymentRequest(PaymentRequestInterface::ACTION_CAPTURE, $this->methodOf(SyliusPayPalExtension::PAYPAL_FACTORY_NAME), '2026-10-16 10:15:00');

        $this->paymentRequestRepository->method('findByPaymentIdAndStates')->with(1, [PaymentRequestInterface::STATE_NEW, PaymentRequestInterface::STATE_PROCESSING])->willReturn([$olderAttempt, $latestAttempt, $statusCheck, $otherMethodAttempt]);
        $this->paymentRequestRepository->expects(self::never())->method('add');
        $this->payUrlProvider->method('getUrl')->with($latestAttempt)->willReturn('/en_US/payment-request/pay/LATEST');

        $response = $this->provider->getResponse(new Request(), $this->order);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/en_US/payment-request/pay/LATEST', $response->getTargetUrl());
    }

    public function test_it_starts_a_new_attempt_for_the_default_action_when_the_payment_has_no_open_one(): void
    {
        $paymentMethod = $this->methodOf(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $payment = $this->paymentWith($paymentMethod);
        $this->paymentToPayResolver->method('getPayment')->willReturn($payment);
        $this->paymentRequestRepository->method('findByPaymentIdAndStates')->willReturn([$this->paymentRequest(PaymentRequestInterface::ACTION_CAPTURE, $paymentMethod, '2026-10-16 10:00:00')]);

        $newAttempt = new PaymentRequest($payment, $paymentMethod);
        $this->paymentRequestFactory->method('create')->with($payment, $paymentMethod)->willReturn($newAttempt);
        $this->defaultActionProvider->method('getAction')->with($newAttempt)->willReturn(PaymentRequestInterface::ACTION_AUTHORIZE);
        $this->paymentRequestRepository->expects(self::once())->method('add')->with($newAttempt);
        $this->payUrlProvider->method('getUrl')->with($newAttempt)->willReturn('/en_US/payment-request/pay/NEW');

        $response = $this->provider->getResponse(new Request(), $this->order);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/en_US/payment-request/pay/NEW', $response->getTargetUrl());
        self::assertSame(PaymentRequestInterface::ACTION_AUTHORIZE, $newAttempt->getAction());
        self::assertSame(['default' => 'payload'], $newAttempt->getPayload());
    }

    private function methodOf(string $factoryName): PaymentMethodInterface
    {
        $gatewayConfig = $this->createStub(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);

        $paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        return $paymentMethod;
    }

    private function paymentWith(PaymentMethodInterface $paymentMethod): PaymentInterface
    {
        $payment = $this->createStub(PaymentInterface::class);
        $payment->method('getId')->willReturn(1);
        $payment->method('getMethod')->willReturn($paymentMethod);

        return $payment;
    }

    private function paymentRequest(string $action, PaymentMethodInterface $paymentMethod, string $createdAt): PaymentRequestInterface
    {
        $paymentRequest = $this->createStub(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn($action);
        $paymentRequest->method('getMethod')->willReturn($paymentMethod);
        $paymentRequest->method('getCreatedAt')->willReturn(new \DateTime($createdAt));

        return $paymentRequest;
    }
}
