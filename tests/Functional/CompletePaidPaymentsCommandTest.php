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

namespace Tests\Sylius\PayPalPlugin\Functional;

use ApiTestCase\JsonApiTestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CompletePaidPaymentsCommandTest extends JsonApiTestCase
{
    use MocksPayPalApiTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadFixturesFromFiles(['resources/shop.yaml', 'resources/shipping.yaml', 'resources/authorized_paypal_order.yaml']);
    }

    public function test_it_completes_an_authorized_payment_whose_authorization_was_captured_at_paypal(): void
    {
        $this->payPalApi()->mockOrderDetailsWithCapturedAuthorization();

        self::assertSame(Command::SUCCESS, $this->completePayments());

        $payment = $this->reloadedPayment();
        self::assertSame(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertSame('CAPTURED', $payment->getDetails()['authorization_status']);
    }

    public function test_it_cancels_an_authorized_payment_whose_authorization_was_voided_at_paypal(): void
    {
        $this->payPalApi()->mockOrderDetailsWithAuthorization(authorizationStatus: 'VOIDED');

        self::assertSame(Command::SUCCESS, $this->completePayments());

        $payment = $this->reloadedPayment();
        self::assertSame(PaymentInterface::STATE_CANCELLED, $payment->getState());
        self::assertSame('VOIDED', $payment->getDetails()['authorization_status']);
    }

    public function test_it_leaves_an_authorization_paypal_still_holds_alone(): void
    {
        $this->payPalApi()->mockOrderDetailsWithAuthorization();

        self::assertSame(Command::SUCCESS, $this->completePayments());

        self::assertSame(PaymentInterface::STATE_AUTHORIZED, $this->reloadedPayment()->getState());
    }

    private function completePayments(): int
    {
        $commandTester = new CommandTester((new Application(self::$kernel))->find('sylius-paypal:complete-payments'));

        return $commandTester->execute([]);
    }

    private function reloadedPayment(): PaymentInterface
    {
        $this->getEntityManager()->clear();

        /** @var OrderInterface $order */
        $order = self::getContainer()->get('sylius.repository.order')->findOneBy(['tokenValue' => 'AUTHORIZED_TOKEN']);

        /** @var PaymentInterface $payment */
        $payment = $order->getPayments()->first();

        return $payment;
    }
}
