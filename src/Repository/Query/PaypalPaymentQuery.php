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
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;

final class PaypalPaymentQuery implements PaypalPaymentQueryInterface, SettleablePaypalPaymentQueryInterface
{
    /** @param PaymentRepositoryInterface<PaymentInterface>&EntityRepository $paymentRepository */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentRepositoryInterface&EntityRepository $paymentRepository,
        private readonly array $updatableStates = ['cart', 'new', 'processing'],
        private readonly array $cancellableStates = ['cart', 'new', 'processing', 'completed'],
        private readonly array $refundableStates = ['completed'],
        private readonly array $settleableStates = ['processing', 'completed', 'cancelled', 'failed'],
    ) {
    }

    public function getForUpdateByOrderId(string $paypalOrderId): PaymentInterface
    {
        $queryBuilder = $this->getPaypalPaymentQueryBuilder()
            ->andWhere('o.state IN (:states)')
            ->setParameter('states', $this->updatableStates)
            ->addOrderBy('o.createdAt', 'DESC')
        ;

        return $this->doGetPayment($queryBuilder, $paypalOrderId);
    }

    public function getForCancellationByOrderId(string $paypalOrderId): PaymentInterface
    {
        $queryBuilder = $this->getPaypalPaymentQueryBuilder()
            ->andWhere('o.state IN (:states)')
            ->setParameter('states', $this->cancellableStates)
            ->addOrderBy('o.createdAt', 'DESC')
        ;

        return $this->doGetPayment($queryBuilder, $paypalOrderId);
    }

    public function getForRefundingByOrderId(string $paypalOrderId): PaymentInterface
    {
        $queryBuilder = $this->getPaypalPaymentQueryBuilder()
            ->andWhere('o.state IN (:states)')
            ->setParameter('states', $this->refundableStates)
            ->addOrderBy('o.updatedAt', 'DESC')
        ;

        return $this->doGetPayment($queryBuilder, $paypalOrderId);
    }

    public function getForSettlementByOrderId(string $paypalOrderId): PaymentInterface
    {
        $queryBuilder = $this->getPaypalPaymentQueryBuilder()
            ->andWhere('o.state IN (:states)')
            ->setParameter('states', $this->settleableStates)
            ->addOrderBy('o.updatedAt', 'DESC')
        ;

        return $this->doGetPayment($queryBuilder, $paypalOrderId);
    }

    private function doGetPayment(QueryBuilder $queryBuilder, string $paypalOrderId): PaymentInterface
    {
        if ($this->isCastAvailable()) {
            $payment = $queryBuilder
                ->andWhere('CAST(o.details AS text) LIKE :orderId')
                ->setParameter('orderId', '%"' . $paypalOrderId . '"%')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult()
            ;
            if (null !== $payment) {
                return $payment;
            }

            throw new PaymentNotFoundException();
        }

        $payments = $queryBuilder
            ->getQuery()
            ->toIterable()
        ;

        foreach ($payments as $payment) {
            $details = $payment->getDetails();
            if (isset($details['paypal_order_id']) && $details['paypal_order_id'] === $paypalOrderId) {
                return $payment;
            }
        }

        throw new PaymentNotFoundException();
    }

    private function getPaypalPaymentQueryBuilder(): QueryBuilder
    {
        return $this->paymentRepository
            ->createQueryBuilder('o')
            ->innerJoin('o.method', 'method')
            ->innerJoin('method.gatewayConfig', 'gatewayConfig')
            ->andWhere('gatewayConfig.factoryName = :factoryName')
            ->setParameter('factoryName', SyliusPayPalExtension::PAYPAL_FACTORY_NAME)
        ;
    }

    private function isCastAvailable(): bool
    {
        return null !== $this->entityManager->getConfiguration()->getCustomStringFunction('CAST');
    }
}
