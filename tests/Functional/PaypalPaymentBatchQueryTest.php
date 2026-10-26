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

declare(strict_types=1);

namespace Tests\Sylius\PayPalPlugin\Functional;

use ApiTestCase\JsonApiTestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentBatchQueryInterface;

final class PaypalPaymentBatchQueryTest extends JsonApiTestCase
{
    public function test_it_walks_every_paypal_payment_in_the_given_states_batch_by_batch(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/shipping.yaml', 'resources/authorized_paypal_order.yaml']);
        /** @var OrderInterface $order */
        $order = $fixtures['authorized_order'];

        $expected = [$fixtures['authorized_paypal_payment']->getId()];
        foreach ([PaymentInterface::STATE_PROCESSING, PaymentInterface::STATE_PROCESSING, PaymentInterface::STATE_COMPLETED] as $state) {
            $payment = $this->addPayment($order, $fixtures['paypal'], $state);
            if (PaymentInterface::STATE_PROCESSING === $state) {
                $expected[] = $payment->getId();
            }
        }
        $this->addPayment($order, $fixtures['cash_on_delivery'], PaymentInterface::STATE_PROCESSING);

        $found = [];
        foreach ($this->query()->findInStates([PaymentInterface::STATE_PROCESSING, PaymentInterface::STATE_AUTHORIZED], 2) as $payment) {
            $found[] = $payment->getId();
        }

        sort($expected);
        self::assertSame($expected, $found);
    }

    private function addPayment(OrderInterface $order, PaymentMethodInterface $paymentMethod, string $state): PaymentInterface
    {
        /** @var PaymentInterface $payment */
        $payment = self::getContainer()->get('sylius.factory.payment')->createNew();
        $payment->setMethod($paymentMethod);
        $payment->setCurrencyCode('USD');
        $payment->setAmount(20);
        $payment->setState($state);
        $order->addPayment($payment);

        $this->getEntityManager()->persist($payment);
        $this->getEntityManager()->flush();

        return $payment;
    }

    private function query(): PaypalPaymentBatchQueryInterface
    {
        /** @var PaypalPaymentBatchQueryInterface $query */
        $query = self::getContainer()->get('sylius_paypal.repository.query.paypal_payment');

        return $query;
    }
}
