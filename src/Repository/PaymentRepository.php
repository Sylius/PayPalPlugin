<?php

namespace Sylius\PayPalPlugin\Repository;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NativeQuery;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\PaymentRepository as BasePaymentRepository;
use Sylius\Component\Core\Model\Payment;

class PaymentRepository implements PaymentRepositoryInterface
{
    private EntityManager $entityManager;
    private BasePaymentRepository $basePaymentRepository;

    public function __construct(EntityManager $entityManager, BasePaymentRepository $basePaymentRepository)
    {
        $this->entityManager = $entityManager;
        $this->basePaymentRepository = $basePaymentRepository;
    }

    public function __call($method, $arguments)
    {
        if (method_exists($this, $method)) {
            return call_user_func_array($this->$method, $arguments);
        }

        return call_user_func_array($this->basePaymentRepository->$method, $arguments);
    }

    public function getByPayPalOrderId($paypalOrderId)
    {
        $builder = $this->basePaymentRepository->createResultSetMappingBuilder('p');
        $builder->addRootEntityFromClassMetadata(Payment::class, 'p');

        $rawQuery = sprintf(
            'SELECT %s FROM %s p WHERE JSON_EXTRACT(details, \'$.paypal_order_id\') = ? LIMIT 0,1',
            $builder->generateSelectClause(),
            $this->entityManager->getClassMetadata(Payment::class)->getTableName()
        );
        /** @var NativeQuery $query */
        $query = $this->entityManager->createNativeQuery($rawQuery, $builder);
        $query->setParameter(1, $paypalOrderId);

        return $query->getOneOrNullResult();
    }

}
