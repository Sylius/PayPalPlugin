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
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;

final class PaymentProvider implements PaymentProviderInterface
{
    private EntityManager $entityManager;
    private PaymentRepositoryInterface $paymentRepository;

    public function __construct(EntityManager $entityManager, PaymentRepositoryInterface $paymentRepository)
    {
        $this->entityManager = $entityManager;
        $this->paymentRepository = $paymentRepository;
    }

    /**
     * @param string $orderId
     * @return PaymentInterface|null
     * @throws PaymentNotFoundException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getByPayPalOrderId(string $orderId): ?PaymentInterface
    {
        /** @var ResultSetMappingBuilder $builder */
        $builder = $this->paymentRepository->createResultSetMappingBuilder('p');
        $builder->addRootEntityFromClassMetadata($this->paymentRepository->getClassName(), 'p');

        $rawQuery = sprintf(
            'SELECT %s FROM sylius_payment p WHERE JSON_EXTRACT(details, \'$.paypal_order_id\') = ? LIMIT 0,1',
            $builder->generateSelectClause(),
        );

        $query = $this->entityManager->createNativeQuery($rawQuery, $builder);
        $query->setParameter(1, $orderId);
        $payment = $query->getOneOrNullResult();

        if (!is_null($payment)) {
            return $payment;
        }

        throw PaymentNotFoundException::withPayPalOrderId($orderId);
    }
}
