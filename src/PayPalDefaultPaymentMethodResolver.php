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
use Sylius\Component\Payment\Exception\UnresolvedDefaultPaymentMethodException;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Resolver\DefaultPaymentMethodResolverInterface;
use Webmozart\Assert\Assert;

class PayPalDefaultPaymentMethodResolver implements DefaultPaymentMethodResolverInterface
{
    /** @var PaymentMethodRepositoryInterface */
    protected $paymentMethodRepository;

    /** @var DefaultPaymentMethodResolverInterface */
    private $decoratedDefaultPaymentMethodResolver;

    public function __construct(
        DefaultPaymentMethodResolverInterface $decoratedDefaultPaymentMethodResolver,
        PaymentMethodRepositoryInterface $paymentMethodRepository
    ) {
        $this->decoratedDefaultPaymentMethodResolver = $decoratedDefaultPaymentMethodResolver;
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnresolvedDefaultPaymentMethodException
     */
    public function getDefaultPaymentMethod(BasePaymentInterface $subject): PaymentMethodInterface
    {
        /** @var PaymentInterface $subject */
        Assert::isInstanceOf($subject, PaymentInterface::class);

        /** @var OrderInterface $order */
        $order = $subject->getOrder();

        /** @var ChannelInterface $channel */
        $channel = $order->getChannel();

        return $this->getFirstPrioritisedPaymentForChannel($channel, 'PayPal');
    }

    private function getFirstPrioritisedPaymentForChannel(ChannelInterface $channel, string $prioritisedPayment): PaymentMethodInterface
    {
        /** @var array<PaymentMethodInterface> $paymentMethods */
        $paymentMethods = $this->paymentMethodRepository->findEnabledForChannel($channel);

        if (empty($paymentMethods)) {
            throw new UnresolvedDefaultPaymentMethodException();
        }

        foreach ($paymentMethods as $payment) {
            if ($payment->getName() === $prioritisedPayment) {
                return $payment;
            }
        }

        return $paymentMethods[0];
    }
}
