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
use Sylius\PayPalPlugin\Model\WebhookState;
use Sylius\PayPalPlugin\Twig\Component\WebhookStateComponent;

final class WebhookStateComponentTest extends TestCase
{
    /** @var PaymentMethodRepositoryInterface<PaymentMethodInterface>&MockObject */
    private PaymentMethodRepositoryInterface&MockObject $paymentMethodRepository;

    private WebhookStateCheckerInterface&MockObject $webhookStateChecker;

    private LoggerInterface&MockObject $logger;

    private WebhookStateComponent $component;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $this->webhookStateChecker = $this->createMock(WebhookStateCheckerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->component = new WebhookStateComponent($this->paymentMethodRepository, $this->webhookStateChecker, $this->logger);
        $this->component->paymentMethodId = 1;
    }

    public function test_it_gives_the_state_of_the_webhook_of_a_paypal_payment_method(): void
    {
        $paymentMethod = $this->paymentMethod('sylius_paypal');
        $this->paymentMethodRepository->method('find')->with(1)->willReturn($paymentMethod);
        $this->webhookStateChecker->method('check')->with($paymentMethod)->willReturn(WebhookState::notRegistered());

        self::assertEquals(WebhookState::notRegistered(), $this->component->getState());
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

    public function test_it_logs_a_failed_check_and_gives_no_state(): void
    {
        $this->paymentMethodRepository->method('find')->willReturn($this->paymentMethod('sylius_paypal'));
        $this->webhookStateChecker->method('check')->willThrowException(new PayPalApiRequestException('GET', 'v1/notifications/webhooks/WH-1', 500));

        $this->logger->expects(self::once())->method('error')->with(self::stringContains('"PAYPAL"'));

        self::assertNull($this->component->getState());
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
