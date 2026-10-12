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
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Repository\Query\PayPalPaymentMethodQueryInterface;

final class PayPalPaymentMethodQueryTest extends JsonApiTestCase
{
    public function test_it_finds_the_paypal_payment_method_only_while_one_exists(): void
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml']);

        $paymentMethod = $this->query()->findOne();

        self::assertInstanceOf(PaymentMethodInterface::class, $paymentMethod);
        self::assertSame('PAYPAL', $paymentMethod->getCode());
        self::assertTrue($this->query()->exists());

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->remove($paymentMethod);
        $entityManager->flush();

        self::assertNull($this->query()->findOne());
        self::assertFalse($this->query()->exists());
    }

    private function query(): PayPalPaymentMethodQueryInterface
    {
        /** @var PayPalPaymentMethodQueryInterface $query */
        $query = self::getContainer()->get('sylius_paypal.repository.query.paypal_payment_method');

        return $query;
    }
}
