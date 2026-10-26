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
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Processor\PayPalPaymentSettlementProcessor;

final class PaypalPaymentQuery implements PaypalPaymentQueryInterface, SettleablePaypalPaymentQueryInterface, PaypalPaymentBatchQueryInterface
{
    /** @param PaymentRepositoryInterface<PaymentInterface>&EntityRepository $paymentRepository */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentRepositoryInterface&EntityRepository $paymentRepository,
        private readonly array $updatableStates = [
            PaymentInterface::STATE_CART,
            PaymentInterface::STATE_NEW,
            PaymentInterface::STATE_PROCESSING,
        ],
        private readonly array $cancellableStates = [
            PaymentInterface::STATE_CART,
            PaymentInterface::STATE_NEW,
            PaymentInterface::STATE_PROCESSING,
            PaymentInterface::STATE_COMPLETED,
        ],
        private readonly array $settleableStates = [
            PaymentInterface::STATE_NEW,
            PaymentInterface::STATE_PROCESSING,
            PaymentInterface::STATE_COMPLETED,
            PaymentInterface::STATE_CANCELLED,
            PaymentInterface::STATE_FAILED,
        ],
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

    public function getForSettlementByOrderId(string $paypalOrderId): PaymentInterface
    {
        $queryBuilder = $this->getPaypalPaymentQueryBuilder()
            ->andWhere('o.state IN (:states)')
            ->setParameter('states', $this->settleableStates)
            ->addOrderBy('o.updatedAt', 'DESC')
        ;

        return $this->doGetPayment($queryBuilder, $paypalOrderId, includingSuperseded: true);
    }

    public function getForSettlementByCaptureId(string $captureId): PaymentInterface
    {
        $queryBuilder = $this->getPaypalPaymentQueryBuilder()
            ->andWhere('o.state IN (:states)')
            ->setParameter('states', $this->settleableStates)
            ->addOrderBy('o.updatedAt', 'DESC')
        ;

        $this->filterByDetailsValue($queryBuilder, $captureId);

        /** @var PaymentInterface $payment */
        foreach ($queryBuilder->getQuery()->toIterable() as $payment) {
            $lateCapture = $payment->getDetails()[PayPalPaymentSettlementProcessor::LATE_CAPTURE] ?? null;
            if (
                $captureId === PayPalPaymentDetails::fromPayment($payment)->transactionId() ||
                (is_array($lateCapture) && $captureId === ($lateCapture['id'] ?? null))
            ) {
                return $payment;
            }
        }

        throw new PaymentNotFoundException();
    }

    public function findInStates(array $states, int $limit, ?int $afterId = null): array
    {
        $queryBuilder = $this->getPaypalPaymentQueryBuilder()
            ->andWhere('o.state IN (:states)')
            ->setParameter('states', $states)
            ->orderBy('o.id', 'ASC')
            ->setMaxResults($limit)
        ;

        if (null !== $afterId) {
            $queryBuilder
                ->andWhere('o.id > :afterId')
                ->setParameter('afterId', $afterId)
            ;
        }

        /** @var list<PaymentInterface> $payments */
        $payments = $queryBuilder->getQuery()->getResult();

        return $payments;
    }

    private function doGetPayment(QueryBuilder $queryBuilder, string $paypalOrderId, bool $includingSuperseded = false): PaymentInterface
    {
        $this->filterByDetailsValue($queryBuilder, $paypalOrderId);

        /** @var PaymentInterface $payment */
        foreach ($queryBuilder->getQuery()->toIterable() as $payment) {
            $details = PayPalPaymentDetails::fromPayment($payment);
            if (
                $paypalOrderId === $details->payPalOrderId() ||
                ($includingSuperseded && $details->isSupersededPayPalOrder($paypalOrderId))
            ) {
                return $payment;
            }
        }

        throw new PaymentNotFoundException();
    }

    private function filterByDetailsValue(QueryBuilder $queryBuilder, string $value): void
    {
        if ($this->isCastAvailable()) {
            $queryBuilder
                ->andWhere('CAST(o.details AS text) LIKE :detailsValue')
                ->setParameter('detailsValue', '%"' . $value . '"%')
            ;
        }
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
