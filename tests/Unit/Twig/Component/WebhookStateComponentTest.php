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

namespace Tests\Sylius\PayPalPlugin\Unit\Twig\Component;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Checker\WebhookStateCheckerInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookNotRegisteredException;
use Sylius\PayPalPlugin\Model\WebhookRegistration;
use Sylius\PayPalPlugin\Model\WebhookState;
use Sylius\PayPalPlugin\Registrar\SellerWebhookEventTypesRegistrarInterface;
use Sylius\PayPalPlugin\Twig\Component\WebhookStateComponent;

final class WebhookStateComponentTest extends TestCase
{
    /** @var PaymentMethodRepositoryInterface<PaymentMethodInterface>&MockObject */
    private PaymentMethodRepositoryInterface&MockObject $paymentMethodRepository;

    private WebhookStateCheckerInterface&MockObject $webhookStateChecker;

    private SellerWebhookEventTypesRegistrarInterface&MockObject $registrar;

    private LoggerInterface&MockObject $logger;

    private WebhookStateComponent $component;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $this->webhookStateChecker = $this->createMock(WebhookStateCheckerInterface::class);
        $this->registrar = $this->createMock(SellerWebhookEventTypesRegistrarInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->component = new WebhookStateComponent(
            $this->paymentMethodRepository,
            $this->webhookStateChecker,
            $this->registrar,
            $this->logger,
        );
        $this->component->paymentMethodId = 1;
    }

    public function test_it_gives_the_state_of_the_webhook_of_a_paypal_payment_method(): void
    {
        $paymentMethod = $this->paymentMethod('sylius_paypal');
        $this->paymentMethodRepository->method('find')->with(1)->willReturn($paymentMethod);
        $this->webhookStateChecker->method('check')->with($paymentMethod)->willReturn(WebhookState::unknown());

        self::assertEquals(WebhookState::unknown(), $this->component->getState());
    }

    public function test_it_gives_no_state_for_another_gateway(): void
    {
        $this->paymentMethodRepository->method('find')->willReturn($this->paymentMethod('offline'));

        $this->webhookStateChecker->expects(self::never())->method('check');

        self::assertNull($this->component->getState());
    }

    public function test_it_gives_no_state_for_a_payment_method_that_does_not_exist(): void
    {
        $this->paymentMethodRepository->method('find')->willReturn(null);

        self::assertNull($this->component->getState());
    }

    public function test_it_updates_the_webhook(): void
    {
        $paymentMethod = $this->paymentMethod('sylius_paypal');
        $this->paymentMethodRepository->method('find')->willReturn($paymentMethod);
        $registration = WebhookRegistration::updated('WH-1', 'https://shop.example.com/payment-methods/PAYPAL');

        $this->registrar->expects(self::once())->method('register')->with($paymentMethod)->willReturn($registration);

        $this->component->updateWebhook();

        self::assertSame($registration, $this->component->registration);
        self::assertFalse($this->component->updateFailed);
        self::assertNull($this->component->updateError);
    }

    public function test_it_tells_why_the_webhook_could_not_be_updated(): void
    {
        $paymentMethod = $this->paymentMethod('sylius_paypal');
        $this->paymentMethodRepository->method('find')->willReturn($paymentMethod);
        $this->registrar->method('register')->willThrowException(new PayPalWebhookNotRegisteredException('PAYPAL', 'https://localhost/payment-methods/PAYPAL', 'VALIDATION_ERROR'));

        $this->logger->expects(self::once())->method('error')->with(self::stringContains('"PAYPAL"'));

        $this->component->updateWebhook();

        self::assertNull($this->component->registration);
        self::assertTrue($this->component->updateFailed);
        self::assertSame(
            'PayPal has no webhook for the payment method "PAYPAL" and refused to register one at "https://localhost/payment-methods/PAYPAL": VALIDATION_ERROR.',
            $this->component->updateError,
        );
    }

    public function test_it_tells_what_paypal_answered_when_it_refused_to_update_the_webhook(): void
    {
        $this->paymentMethodRepository->method('find')->willReturn($this->paymentMethod('sylius_paypal'));
        $this->registrar->method('register')->willThrowException(
            new PayPalApiRequestException('PATCH', 'v1/notifications/webhooks/WH-1', 400, 'VALIDATION_ERROR', debugId: 'DEBUG_ID'),
        );

        $this->component->updateWebhook();

        self::assertTrue($this->component->updateFailed);
        self::assertSame('VALIDATION_ERROR (debug id: DEBUG_ID)', $this->component->updateError);
    }

    public function test_it_keeps_the_reason_of_any_other_failure_in_the_log(): void
    {
        $this->paymentMethodRepository->method('find')->willReturn($this->paymentMethod('sylius_paypal'));
        $exception = new \RuntimeException('SQLSTATE[HY000]: General error');
        $this->registrar->method('register')->willThrowException($exception);

        $this->logger->expects(self::once())->method('error')->with(self::stringContains('SQLSTATE[HY000]'), ['exception' => $exception]);

        $this->component->updateWebhook();

        self::assertTrue($this->component->updateFailed);
        self::assertNull($this->component->updateError);
    }

    public function test_it_does_not_update_the_webhook_of_another_gateway(): void
    {
        $this->paymentMethodRepository->method('find')->willReturn($this->paymentMethod('offline'));

        $this->registrar->expects(self::never())->method('register');

        $this->component->updateWebhook();
    }

    private function paymentMethod(string $factoryName): PaymentMethodInterface
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName($factoryName);

        $paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $paymentMethod->method('getCode')->willReturn('PAYPAL');
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        return $paymentMethod;
    }
}
