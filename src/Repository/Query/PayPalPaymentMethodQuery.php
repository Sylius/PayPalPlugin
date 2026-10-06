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

namespace Sylius\PayPalPlugin\Repository\Query;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;

final readonly class PayPalPaymentMethodQuery implements PayPalPaymentMethodQueryInterface
{
    /** @param class-string<PaymentMethodInterface> $paymentMethodClass */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $paymentMethodClass,
    ) {
    }

    public function findOne(): ?PaymentMethodInterface
    {
        /** @var PaymentMethodInterface|null $paymentMethod */
        $paymentMethod = $this->getPayPalPaymentMethodQueryBuilder()
            ->addOrderBy('o.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $paymentMethod;
    }

    public function exists(): bool
    {
        $id = $this->getPayPalPaymentMethodQueryBuilder()
            ->select('o.id')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return null !== $id;
    }

    private function getPayPalPaymentMethodQueryBuilder(): QueryBuilder
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('o')
            ->from($this->paymentMethodClass, 'o')
            ->innerJoin('o.gatewayConfig', 'gatewayConfig')
            ->andWhere('gatewayConfig.factoryName = :factoryName')
            ->setParameter('factoryName', SyliusPayPalExtension::PAYPAL_FACTORY_NAME)
        ;
    }
}
