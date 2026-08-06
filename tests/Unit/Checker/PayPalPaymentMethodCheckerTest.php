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

namespace Tests\Sylius\PayPalPlugin\Unit\Checker;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\PaymentMethodRepository;
use Sylius\PayPalPlugin\Checker\PayPalPaymentMethodChecker;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;

final class PayPalPaymentMethodCheckerTest extends TestCase
{
    private MockObject|PaymentMethodRepository $paymentMethodRepository;

    private PayPalPaymentMethodChecker $checker;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepository::class);

        $this->checker = new PayPalPaymentMethodChecker($this->paymentMethodRepository);
    }

    public function testReturnsTrueWhenAtLeastOnePayPalPaymentMethodExists(): void
    {
        $this->mockCountQuery(2);

        self::assertTrue($this->checker->hasPayPalPaymentMethod());
    }

    public function testReturnsFalseWhenNoPayPalPaymentMethodExists(): void
    {
        $this->mockCountQuery(0);

        self::assertFalse($this->checker->hasPayPalPaymentMethod());
    }

    private function mockCountQuery(int $count): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn((string) $count);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')
            ->with('factoryName', SyliusPayPalExtension::PAYPAL_FACTORY_NAME)
            ->willReturnSelf()
        ;
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->paymentMethodRepository
            ->method('createQueryBuilder')
            ->with('o')
            ->willReturn($queryBuilder)
        ;
    }
}
