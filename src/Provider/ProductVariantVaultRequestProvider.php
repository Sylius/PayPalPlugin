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

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Model\PayPalVaultRequirement;
use Sylius\Resource\Factory\FactoryInterface;

final readonly class ProductVariantVaultRequestProvider implements ProductVariantVaultRequestProviderInterface
{
    /**
     * @param FactoryInterface<OrderInterface> $orderFactory
     * @param FactoryInterface<OrderItemInterface> $orderItemFactory
     */
    public function __construct(
        private FactoryInterface $orderFactory,
        private FactoryInterface $orderItemFactory,
        private OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        private PayPalVaultRequestProviderInterface $vaultRequestProvider,
    ) {
    }

    public function provide(ProductVariantInterface $variant, ChannelInterface $channel, ?PaymentMethodInterface $paymentMethod): PayPalVaultRequirement
    {
        if (null === $paymentMethod) {
            return PayPalVaultRequirement::notRequired();
        }

        $orderItem = $this->orderItemFactory->createNew();
        $orderItem->setVariant($variant);
        $this->orderItemQuantityModifier->modify($orderItem, 1);

        $order = $this->orderFactory->createNew();
        $order->setChannel($channel);
        $order->addItem($orderItem);

        return $this->vaultRequestProvider->provide($order, $paymentMethod);
    }
}
