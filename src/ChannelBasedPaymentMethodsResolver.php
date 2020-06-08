<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\Resolver\PaymentMethodsResolverInterface;
use Webmozart\Assert\Assert;

final class ChannelBasedPaymentMethodsResolver implements PaymentMethodsResolverInterface
{
    /** @var PaymentMethodRepositoryInterface */
    private $paymentMethodRepository;

    public function __construct(PaymentMethodRepositoryInterface $paymentMethodRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedMethods(BasePaymentInterface $payment): array
    {
        /** @var PaymentInterface $payment */
        Assert::isInstanceOf($payment, PaymentInterface::class);
        Assert::true($this->supports($payment), 'This payment method is not support by resolver');

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        /** @var ChannelInterface $channel */
        $channel = $order->getChannel();

        return $this->sortPayments(
            $this->paymentMethodRepository->findEnabledForChannel($channel),
            'sylius.pay_pal'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function supports(BasePaymentInterface $payment): bool
    {
        return $payment instanceof PaymentInterface &&
            null !== $payment->getOrder() &&
            null !== $payment->getOrder()->getChannel()
            ;
    }

    private function sortPayments(array $payments, string $first_payment): array
    {
        /** @var array $sorted_payments */
        $sorted_payments = [];

        foreach ($payments as $payment) {
            if ($payment->getGatewayConfig()->getFactoryName() === $first_payment) {
                array_unshift($sorted_payments, $payment);
            } else {
                $sorted_payments[] = $payment;
            }
        }

        return $sorted_payments;
    }
}
