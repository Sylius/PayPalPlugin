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
    public function test_it_finds_all_paypal_payment_methods(): void
    {
        $this->loadFixturesFromFiles(['resources/shop.yaml']);

        $paymentMethods = $this->query()->findAll();

        self::assertSame(['PAYPAL'], array_map(
            static fn (PaymentMethodInterface $paymentMethod): ?string => $paymentMethod->getCode(),
            $paymentMethods,
        ));

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->remove($paymentMethods[0]);
        $entityManager->flush();

        self::assertSame([], $this->query()->findAll());
    }

    private function query(): PayPalPaymentMethodQueryInterface
    {
        /** @var PayPalPaymentMethodQueryInterface $query */
        $query = self::getContainer()->get('sylius_paypal.repository.query.paypal_payment_method');

        return $query;
    }
}
