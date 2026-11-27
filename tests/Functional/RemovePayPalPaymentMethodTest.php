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
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Entity\PayPalCredentials;

final class RemovePayPalPaymentMethodTest extends JsonApiTestCase
{
    public function test_it_removes_a_paypal_payment_method_with_a_cached_access_token(): void
    {
        $fixtures = $this->loadFixturesFromFiles(['resources/shop.yaml']);
        $entityManager = $this->getEntityManager();

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $fixtures['paypal'];
        $entityManager->persist(new PayPalCredentials('CREDENTIALS_ID', $paymentMethod, 'ACCESS_TOKEN', new \DateTime(), 3600));
        $entityManager->flush();
        $paymentMethodId = $paymentMethod->getId();

        $entityManager->remove($paymentMethod);
        $entityManager->flush();
        $entityManager->clear();

        self::assertNull($entityManager->getRepository($paymentMethod::class)->find($paymentMethodId));
        self::assertSame([], $entityManager->getRepository(PayPalCredentials::class)->findAll());
    }
}
