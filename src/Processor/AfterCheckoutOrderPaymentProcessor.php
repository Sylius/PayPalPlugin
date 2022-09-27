<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Processor;

use Sylius\Component\Core\Model\OrderInterface as CoreOrderInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Webmozart\Assert\Assert;

final class AfterCheckoutOrderPaymentProcessor implements OrderProcessorInterface
{
    private OrderProcessorInterface $baseAfterCheckoutOrderPaymentProcessor;

    public function __construct(OrderProcessorInterface $baseAfterCheckoutOrderPaymentProcessor)
    {
        $this->baseAfterCheckoutOrderPaymentProcessor = $baseAfterCheckoutOrderPaymentProcessor;
    }

    /**
     * @param CoreOrderInterface $order
     */
    public function process(OrderInterface $order): void
    {
        Assert::isInstanceOf($order, CoreOrderInterface::class);

        if ($order->getCheckoutState() !== OrderCheckoutStates::STATE_COMPLETED) {
            return;
        }

        $this->baseAfterCheckoutOrderPaymentProcessor->process($order);
    }
}
