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

namespace Tests\Sylius\PayPalPlugin\Unit\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\PayPalPlugin\Model\PayPalVaultRequest;
use Sylius\PayPalPlugin\Model\PayPalVaultRequirement;
use Sylius\PayPalPlugin\Provider\PayPalVaultRequestProviderInterface;
use Sylius\PayPalPlugin\Provider\ProductVariantVaultRequestProvider;
use Sylius\Resource\Factory\FactoryInterface;

final class ProductVariantVaultRequestProviderTest extends TestCase
{
    private OrderItemQuantityModifierInterface&MockObject $orderItemQuantityModifier;

    private PayPalVaultRequestProviderInterface&MockObject $vaultRequestProvider;

    private ProductVariantVaultRequestProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var FactoryInterface<OrderInterface>&MockObject $orderFactory */
        $orderFactory = $this->createMock(FactoryInterface::class);
        $orderFactory->method('createNew')->willReturnCallback(static fn (): Order => new Order());
        /** @var FactoryInterface<OrderItemInterface>&MockObject $orderItemFactory */
        $orderItemFactory = $this->createMock(FactoryInterface::class);
        $orderItemFactory->method('createNew')->willReturnCallback(static fn (): OrderItem => new OrderItem());
        $this->orderItemQuantityModifier = $this->createMock(OrderItemQuantityModifierInterface::class);
        $this->vaultRequestProvider = $this->createMock(PayPalVaultRequestProviderInterface::class);
        $this->provider = new ProductVariantVaultRequestProvider(
            $orderFactory,
            $orderItemFactory,
            $this->orderItemQuantityModifier,
            $this->vaultRequestProvider,
        );
    }

    #[Test]
    public function it_asks_about_a_cart_of_the_channel_holding_one_unit_of_the_variant(): void
    {
        $variant = $this->createStub(ProductVariantInterface::class);
        $channel = $this->createStub(ChannelInterface::class);
        $paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $requirement = PayPalVaultRequirement::met(new PayPalVaultRequest(PayPalVaultRequest::USAGE_PATTERN_SUBSCRIPTION_PREPAID));

        $this->orderItemQuantityModifier
            ->expects(self::once())
            ->method('modify')
            ->with(self::callback(static fn (OrderItemInterface $orderItem): bool => $variant === $orderItem->getVariant()), 1)
        ;
        $this->vaultRequestProvider
            ->expects(self::once())
            ->method('provide')
            ->with(
                self::callback(static fn (OrderInterface $order): bool => OrderInterface::STATE_CART === $order->getState() &&
                    $channel === $order->getChannel() &&
                    1 === $order->getItems()->count() &&
                    $variant === $order->getItems()->first()->getVariant()),
                $paymentMethod,
            )
            ->willReturn($requirement)
        ;

        self::assertSame($requirement, $this->provider->provide($variant, $channel, $paymentMethod));
    }

    #[Test]
    public function it_does_not_require_vaulting_without_a_payment_method(): void
    {
        $this->vaultRequestProvider->expects(self::never())->method('provide');

        self::assertNull(
            $this->provider->provide(
                $this->createStub(ProductVariantInterface::class),
                $this->createStub(ChannelInterface::class),
                null,
            )->vaultRequest(),
        );
    }
}
