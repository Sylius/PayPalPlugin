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

namespace Tests\Sylius\PayPalPlugin\Unit\Checker;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Checker\CachingWebhookStateChecker;
use Sylius\PayPalPlugin\Checker\WebhookStateCacheInterface;
use Sylius\PayPalPlugin\Checker\WebhookStateCheckerInterface;
use Sylius\PayPalPlugin\Exception\PayPalApiRequestException;
use Sylius\PayPalPlugin\Model\WebhookState;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CachingWebhookStateCheckerTest extends TestCase
{
    private WebhookStateCheckerInterface&MockObject $webhookStateChecker;

    private CachingWebhookStateChecker $checker;

    protected function setUp(): void
    {
        $this->webhookStateChecker = $this->createMock(WebhookStateCheckerInterface::class);
        $this->checker = new CachingWebhookStateChecker($this->webhookStateChecker, new ArrayAdapter(), 3600);
    }

    public function test_it_implements_the_webhook_state_checker_interface(): void
    {
        self::assertInstanceOf(WebhookStateCheckerInterface::class, $this->checker);
    }

    public function test_it_asks_paypal_once_and_then_answers_from_the_cache(): void
    {
        $state = WebhookState::outdated('https://shop.example.com/payment-methods/PAYPAL', false, ['PAYMENT.CAPTURE.DENIED']);
        $this->webhookStateChecker->expects(self::once())->method('check')->willReturn($state);

        self::assertEquals($state, $this->checker->check($this->paymentMethod('PAYPAL', 'WH-1')));
        self::assertEquals($state, $this->checker->check($this->paymentMethod('PAYPAL', 'WH-1')));
    }

    public function test_it_checks_every_payment_method_and_webhook_on_its_own(): void
    {
        $this->webhookStateChecker->expects(self::exactly(3))->method('check')->willReturn(WebhookState::notRegistered());

        $this->checker->check($this->paymentMethod('PAYPAL', 'WH-1'));
        $this->checker->check($this->paymentMethod('PAYPAL_2', 'WH-1'));
        $this->checker->check($this->paymentMethod('PAYPAL', 'WH-2'));
    }

    public function test_it_asks_paypal_again_once_the_state_of_a_payment_method_is_forgotten(): void
    {
        $this->webhookStateChecker
            ->expects(self::exactly(2))
            ->method('check')
            ->willReturnOnConsecutiveCalls(WebhookState::notRegistered(), WebhookState::upToDate('https://shop.example.com/payment-methods/PAYPAL'))
        ;

        self::assertInstanceOf(WebhookStateCacheInterface::class, $this->checker);
        $this->checker->check($this->paymentMethod('PAYPAL', 'WH-1'));
        $this->checker->forget($this->paymentMethod('PAYPAL', 'WH-1'));

        self::assertTrue($this->checker->check($this->paymentMethod('PAYPAL', 'WH-1'))->isUpToDate());
    }

    public function test_it_does_not_cache_an_error(): void
    {
        $this->webhookStateChecker
            ->expects(self::exactly(2))
            ->method('check')
            ->willReturnOnConsecutiveCalls(
                self::throwException(new PayPalApiRequestException('GET', 'v1/notifications/webhooks/WH-1', 500)),
                WebhookState::notRegistered(),
            )
        ;

        try {
            $this->checker->check($this->paymentMethod('PAYPAL', 'WH-1'));
            self::fail('The error should have been let through.');
        } catch (PayPalApiRequestException) {
        }

        self::assertEquals(WebhookState::notRegistered(), $this->checker->check($this->paymentMethod('PAYPAL', 'WH-1')));
    }

    private function paymentMethod(string $code, string $webhookId): PaymentMethodInterface
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setConfig(['sandbox' => true, 'webhook_id' => $webhookId]);

        $paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $paymentMethod->method('getCode')->willReturn($code);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        return $paymentMethod;
    }
}
