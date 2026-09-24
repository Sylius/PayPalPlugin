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

namespace Sylius\PayPalPlugin\PackageTracking\Repository;

use Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\PayPalPlugin\PackageTracking\Entity\ShipmentTracking;
use Sylius\PayPalPlugin\PackageTracking\Entity\ShipmentTrackingInterface;

/**
 * @extends EntityRepository<ShipmentTracking>
 */
class ShipmentTrackingRepository extends EntityRepository implements ShipmentTrackingRepositoryInterface
{
    public function findOneByShipment(ShipmentInterface $shipment): ?ShipmentTrackingInterface
    {
        return $this->findOneBy(['shipment' => $shipment]);
    }

    public function findPendingOrFailed(?int $limit = null, ?int $afterId = null): array
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->andWhere('t.state IN (:states)')
            ->setParameter('states', [ShipmentTrackingInterface::STATE_PENDING, ShipmentTrackingInterface::STATE_FAILED])
            ->orderBy('t.id', 'ASC')
        ;

        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        if (null !== $afterId) {
            $queryBuilder
                ->andWhere('t.id > :afterId')
                ->setParameter('afterId', $afterId)
            ;
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
