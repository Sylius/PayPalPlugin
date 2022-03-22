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

namespace Sylius\PayPalPlugin\Provider;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;

final class PaymentProvider implements PaymentProviderInterface
{
    private EntityManager $entityManager;
    private PaymentRepositoryInterface $paymentRepository;

    public function __construct(EntityManager $entityManager, PaymentRepositoryInterface $paymentRepository)
    {
        $this->entityManager = $entityManager;
        $this->paymentRepository = $paymentRepository;
    }

    public function getByPayPalOrderId(string $orderId): PaymentInterface
    {
        /** @var ResultSetMappingBuilder $builder */
        $builder = $this->paymentRepository->createResultSetMappingBuilder('p');
        $builder->addRootEntityFromClassMetadata($this->paymentRepository->getClassName(), 'p');

        $rawQuery = sprintf(
            'SELECT %s FROM sylius_payment p WHERE p.details IS NOT NULL AND p.details != \'\' AND JSON_EXTRACT(p.details, \'$.paypal_order_id\') = ? LIMIT 0,1',
            $builder->generateSelectClause(),
        );

        $query = $this->entityManager->createNativeQuery($rawQuery, $builder);
        $query->setParameter(1, $orderId);

        return $query->getOneOrNullResult();
    }
}
