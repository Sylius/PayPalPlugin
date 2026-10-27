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

namespace Tests\Sylius\PayPalPlugin\Unit\Repository\Query;

use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\PaymentRepository;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQuery;

final class PaypalPaymentQueryTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;

    private MockObject|PaymentRepositoryInterface $paymentRepository;

    private PaypalPaymentQuery $query;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->paymentRepository = $this->createMock(PaymentRepository::class);

        $this->query = new PaypalPaymentQuery(
            $this->entityManager,
            $this->paymentRepository,
            [PaymentInterface::STATE_CART, PaymentInterface::STATE_NEW, PaymentInterface::STATE_PROCESSING],
            [PaymentInterface::STATE_CART, PaymentInterface::STATE_NEW, PaymentInterface::STATE_PROCESSING, PaymentInterface::STATE_COMPLETED],
            [PaymentInterface::STATE_COMPLETED],
        );
    }

    public function testGetForUpdateByOrderIdReturnsPaymentWhenCastIsAvailable(): void
    {
        $paypalOrderId = 'PAYPAL123';
        $payment = $this->createMock(PaymentInterface::class);

        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn($payment);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->paymentRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getCustomStringFunction')->with('CAST')->willReturn('SomeFunction');
        $this->entityManager->method('getConfiguration')->willReturn($configuration);

        $result = $this->query->getForUpdateByOrderId($paypalOrderId);

        $this->assertSame($payment, $result);
    }

    public function testGetForUpdateByOrderIdReturnsPaymentWhenCastIsNotAvailable(): void
    {
        $paypalOrderId = 'PAYPAL123';
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn(['paypal_order_id' => 'PAYPAL123']);

        $query = $this->createMock(Query::class);
        $query->method('toIterable')->willReturn([$payment]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->paymentRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getCustomStringFunction')->with('CAST')->willReturn(null);
        $this->entityManager->method('getConfiguration')->willReturn($configuration);

        $result = $this->query->getForUpdateByOrderId($paypalOrderId);

        $this->assertSame($payment, $result);
    }

    public function testGetForUpdateByOrderIdThrowsExceptionWhenNoPaymentFound(): void
    {
        $paypalOrderId = 'PAYPAL123';

        $query = $this->createMock(Query::class);
        $query->method('toIterable')->willReturn([]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->paymentRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getCustomStringFunction')->with('CAST')->willReturn(null);
        $this->entityManager->method('getConfiguration')->willReturn($configuration);

        $this->expectException(PaymentNotFoundException::class);

        $this->query->getForUpdateByOrderId($paypalOrderId);
    }

    public function testGetForUpdateByOrderIdThrowsExceptionWhenPaymentDoesNotMatchOrderId(): void
    {
        $paypalOrderId = 'PAYPAL123';
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn(['paypal_order_id' => 'DIFFERENT_ID']);

        $query = $this->createMock(Query::class);
        $query->method('toIterable')->willReturn([$payment]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->paymentRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getCustomStringFunction')->with('CAST')->willReturn(null);
        $this->entityManager->method('getConfiguration')->willReturn($configuration);

        $this->expectException(PaymentNotFoundException::class);

        $this->query->getForUpdateByOrderId($paypalOrderId);
    }

    public function testGetForUpdateByOrderIdThrowsExceptionWhenDetailsDoNotContainPaypalOrderId(): void
    {
        $paypalOrderId = 'PAYPAL123';
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn(['other_field' => 'value']);

        $query = $this->createMock(Query::class);
        $query->method('toIterable')->willReturn([$payment]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->paymentRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getCustomStringFunction')->with('CAST')->willReturn(null);
        $this->entityManager->method('getConfiguration')->willReturn($configuration);

        $this->expectException(PaymentNotFoundException::class);

        $this->query->getForUpdateByOrderId($paypalOrderId);
    }

    public function testGetForCancellationByOrderIdReturnsPaymentWithCancellableStates(): void
    {
        $paypalOrderId = 'PAYPAL123';
        $payment = $this->createMock(PaymentInterface::class);

        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn($payment);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->paymentRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getCustomStringFunction')->with('CAST')->willReturn('SomeFunction');
        $this->entityManager->method('getConfiguration')->willReturn($configuration);

        $result = $this->query->getForCancellationByOrderId($paypalOrderId);

        $this->assertSame($payment, $result);
    }

    public function testGetForRefundingByOrderIdReturnsPaymentWithRefundableStates(): void
    {
        $paypalOrderId = 'PAYPAL123';
        $payment = $this->createMock(PaymentInterface::class);

        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn($payment);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->paymentRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getCustomStringFunction')->with('CAST')->willReturn('SomeFunction');
        $this->entityManager->method('getConfiguration')->willReturn($configuration);

        $result = $this->query->getForRefundingByOrderId($paypalOrderId);

        $this->assertSame($payment, $result);
    }

    public function testGetForCancellationByOrderIdThrowsExceptionWhenNoPaymentFound(): void
    {
        $paypalOrderId = 'PAYPAL123';

        $query = $this->createMock(Query::class);
        $query->method('toIterable')->willReturn([]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->paymentRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getCustomStringFunction')->with('CAST')->willReturn(null);
        $this->entityManager->method('getConfiguration')->willReturn($configuration);

        $this->expectException(PaymentNotFoundException::class);

        $this->query->getForCancellationByOrderId($paypalOrderId);
    }

    public function testGetForRefundingByOrderIdThrowsExceptionWhenNoPaymentFound(): void
    {
        $paypalOrderId = 'PAYPAL123';

        $query = $this->createMock(Query::class);
        $query->method('toIterable')->willReturn([]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->paymentRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getCustomStringFunction')->with('CAST')->willReturn(null);
        $this->entityManager->method('getConfiguration')->willReturn($configuration);

        $this->expectException(PaymentNotFoundException::class);

        $this->query->getForRefundingByOrderId($paypalOrderId);
    }

    public function testGetForUpdateByOrderIdUsesCorrectStates(): void
    {
        $paypalOrderId = 'PAYPAL123';

        $query = $this->createMock(Query::class);
        $query->method('toIterable')->willReturn([]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnCallback(function (string $param, $value) use ($queryBuilder) {
                if ($param === 'states') {
                    $this->assertEquals(
                        [PaymentInterface::STATE_CART, PaymentInterface::STATE_NEW, PaymentInterface::STATE_PROCESSING],
                        $value,
                    );
                } elseif ($param === 'factoryName') {
                    $this->assertEquals(SyliusPayPalExtension::PAYPAL_FACTORY_NAME, $value);
                }

                return $queryBuilder;
            });
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->paymentRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getCustomStringFunction')->with('CAST')->willReturn(null);
        $this->entityManager->method('getConfiguration')->willReturn($configuration);

        $this->expectException(PaymentNotFoundException::class);

        $this->query->getForUpdateByOrderId($paypalOrderId);
    }

    public function testGetForCancellationByOrderIdUsesCorrectStates(): void
    {
        $paypalOrderId = 'PAYPAL123';

        $query = $this->createMock(Query::class);
        $query->method('toIterable')->willReturn([]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnCallback(function (string $param, $value) use ($queryBuilder) {
                if ($param === 'states') {
                    $this->assertEquals(
                        [PaymentInterface::STATE_CART, PaymentInterface::STATE_NEW, PaymentInterface::STATE_PROCESSING, PaymentInterface::STATE_COMPLETED],
                        $value,
                    );
                } elseif ($param === 'factoryName') {
                    $this->assertEquals(SyliusPayPalExtension::PAYPAL_FACTORY_NAME, $value);
                }

                return $queryBuilder;
            });
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->paymentRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getCustomStringFunction')->with('CAST')->willReturn(null);
        $this->entityManager->method('getConfiguration')->willReturn($configuration);

        $this->expectException(PaymentNotFoundException::class);

        $this->query->getForCancellationByOrderId($paypalOrderId);
    }

    public function testGetForRefundingByOrderIdUsesCorrectStates(): void
    {
        $paypalOrderId = 'PAYPAL123';

        $query = $this->createMock(Query::class);
        $query->method('toIterable')->willReturn([]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnCallback(function (string $param, $value) use ($queryBuilder) {
                if ($param === 'states') {
                    $this->assertEquals([PaymentInterface::STATE_COMPLETED], $value);
                } elseif ($param === 'factoryName') {
                    $this->assertEquals(SyliusPayPalExtension::PAYPAL_FACTORY_NAME, $value);
                }

                return $queryBuilder;
            });
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->paymentRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getCustomStringFunction')->with('CAST')->willReturn(null);
        $this->entityManager->method('getConfiguration')->willReturn($configuration);

        $this->expectException(PaymentNotFoundException::class);

        $this->query->getForRefundingByOrderId($paypalOrderId);
    }

    public function testConstructorAcceptsCustomStates(): void
    {
        $customUpdatableStates = [PaymentInterface::STATE_NEW];
        $customCancellableStates = [PaymentInterface::STATE_CART, PaymentInterface::STATE_NEW];
        $customRefundableStates = [PaymentInterface::STATE_COMPLETED, PaymentInterface::STATE_REFUNDED];

        $query = new PaypalPaymentQuery(
            $this->entityManager,
            $this->paymentRepository,
            $customUpdatableStates,
            $customCancellableStates,
            $customRefundableStates,
        );

        $this->assertInstanceOf(PaypalPaymentQuery::class, $query);
    }

    public function test_it_finds_a_payment_for_settlement_in_any_state_a_late_webhook_may_meet(): void
    {
        $paypalOrderId = 'PAYPAL123';
        $payment = $this->createMock(PaymentInterface::class);

        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn($payment);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $queryBuilder
            ->method('setParameter')
            ->willReturnCallback(function (string $name, mixed $value) use ($queryBuilder): QueryBuilder {
                if ('states' === $name) {
                    self::assertSame([
                        PaymentInterface::STATE_PROCESSING,
                        PaymentInterface::STATE_COMPLETED,
                        PaymentInterface::STATE_CANCELLED,
                        PaymentInterface::STATE_FAILED,
                    ], $value);
                }

                return $queryBuilder;
            })
        ;

        $this->paymentRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getCustomStringFunction')->with('CAST')->willReturn('SomeFunction');
        $this->entityManager->method('getConfiguration')->willReturn($configuration);

        self::assertSame($payment, $this->query->getForSettlementByOrderId($paypalOrderId));
    }
}
