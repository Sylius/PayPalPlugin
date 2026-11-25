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

namespace Tests\Sylius\PayPalPlugin\Unit\EventListener;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\EventListener\RemoveCachedAccessTokenListener;
use Sylius\PayPalPlugin\Remover\CachedAccessTokenRemoverInterface;

final class RemoveCachedAccessTokenListenerTest extends TestCase
{
    private CachedAccessTokenRemoverInterface&MockObject $cachedAccessTokenRemover;

    private RemoveCachedAccessTokenListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cachedAccessTokenRemover = $this->createMock(CachedAccessTokenRemoverInterface::class);

        $this->listener = new RemoveCachedAccessTokenListener($this->cachedAccessTokenRemover);
    }

    #[Test]
    public function it_removes_the_cached_access_token_of_an_updated_paypal_payment_method(): void
    {
        $paymentMethod = $this->paymentMethodWithFactory('sylius_paypal');

        $this->cachedAccessTokenRemover->expects(self::once())->method('remove')->with($paymentMethod);

        ($this->listener)(new ResourceControllerEvent($paymentMethod));
    }

    #[Test]
    public function it_does_nothing_for_other_payment_methods(): void
    {
        $this->cachedAccessTokenRemover->expects(self::never())->method('remove');

        ($this->listener)(new ResourceControllerEvent($this->paymentMethodWithFactory('offline')));
    }

    #[Test]
    public function it_throws_an_exception_if_subject_is_not_a_payment_method(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ($this->listener)(new ResourceControllerEvent(new \stdClass()));
    }

    private function paymentMethodWithFactory(string $factoryName): PaymentMethodInterface&MockObject
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        return $paymentMethod;
    }
}
