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
        );
    }

    public function testGetForUpdateByOrderIdReturnsPaymentWhenCastIsAvailable(): void
    {
        $paypalOrderId = 'PAYPAL123';
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn(['paypal_order_id' => $paypalOrderId]);

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
        $payment->method('getDetails')->willReturn(['paypal_order_id' => $paypalOrderId]);

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
        $configuration->method('getCustomStringFunction')->with('CAST')->willReturn('SomeFunction');
        $this->entityManager->method('getConfiguration')->willReturn($configuration);

        $result = $this->query->getForCancellationByOrderId($paypalOrderId);

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

    public function testConstructorAcceptsCustomStates(): void
    {
        $customUpdatableStates = [PaymentInterface::STATE_NEW];
        $customCancellableStates = [PaymentInterface::STATE_CART, PaymentInterface::STATE_NEW];

        $query = new PaypalPaymentQuery(
            $this->entityManager,
            $this->paymentRepository,
            $customUpdatableStates,
            $customCancellableStates,
        );

        $this->assertInstanceOf(PaypalPaymentQuery::class, $query);
    }

    public function test_it_finds_a_payment_for_settlement_in_any_state_a_late_webhook_may_meet(): void
    {
        $paypalOrderId = 'PAYPAL123';
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn(['paypal_order_id' => $paypalOrderId]);

        $query = $this->createMock(Query::class);
        $query->method('toIterable')->willReturn([$payment]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $queryBuilder
            ->method('setParameter')
            ->willReturnCallback(function (string $name, mixed $value) use ($queryBuilder): QueryBuilder {
                if ('states' === $name) {
                    self::assertSame([
                        PaymentInterface::STATE_NEW,
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

    public function test_it_finds_a_payment_for_settlement_by_a_paypal_order_another_attempt_superseded(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn(['paypal_order_id' => 'CURRENT', 'superseded_paypal_orders' => ['SUPERSEDED' => 'trustly']]);

        $this->paymentsMatchingWithCast([$payment]);

        self::assertSame($payment, $this->query->getForSettlementByOrderId('SUPERSEDED'));
    }

    public function test_it_does_not_cancel_a_payment_by_a_paypal_order_another_attempt_superseded(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn(['paypal_order_id' => 'CURRENT', 'superseded_paypal_orders' => ['SUPERSEDED' => 'paypal']]);

        $this->paymentsMatchingWithCast([$payment]);

        $this->expectException(PaymentNotFoundException::class);

        $this->query->getForCancellationByOrderId('SUPERSEDED');
    }

    public function test_it_finds_a_payment_for_settlement_by_its_capture_id(): void
    {
        $other = $this->createMock(PaymentInterface::class);
        $other->method('getDetails')->willReturn(['paypal_order_id' => 'OTHER', 'transaction_id' => 'CAPTURE-OTHER']);
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn(['paypal_order_id' => 'ORDER', 'transaction_id' => 'CAPTURE']);

        $this->paymentsMatchingWithCast([$other, $payment]);

        self::assertSame($payment, $this->query->getForSettlementByCaptureId('CAPTURE'));
    }

    public function test_it_finds_a_payment_for_settlement_by_the_id_of_its_late_capture(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn(['paypal_order_id' => 'ORDER', 'paypal_late_capture' => ['id' => 'LATE-CAPTURE']]);

        $this->paymentsMatchingWithCast([$payment]);

        self::assertSame($payment, $this->query->getForSettlementByCaptureId('LATE-CAPTURE'));
    }

    public function test_it_does_not_find_a_payment_for_settlement_by_a_capture_id_it_does_not_carry(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn(['paypal_order_id' => 'CAPTURE', 'transaction_id' => 'OTHER']);

        $this->paymentsMatchingWithCast([$payment]);

        $this->expectException(PaymentNotFoundException::class);

        $this->query->getForSettlementByCaptureId('CAPTURE');
    }

    /** @param list<PaymentInterface> $payments */
    private function paymentsMatchingWithCast(array $payments): void
    {
        $query = $this->createMock(Query::class);
        $query->method('toIterable')->willReturn($payments);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->paymentRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getCustomStringFunction')->with('CAST')->willReturn('SomeFunction');
        $this->entityManager->method('getConfiguration')->willReturn($configuration);
    }
}
