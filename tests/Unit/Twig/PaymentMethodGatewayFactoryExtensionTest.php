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

namespace Tests\Sylius\PayPalPlugin\Unit\Twig;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\PaymentMethodRepository;
use Sylius\PayPalPlugin\Twig\PaymentMethodGatewayFactoryExtension;

final class PaymentMethodGatewayFactoryExtensionTest extends TestCase
{
    private MockObject&PaymentMethodRepository $paymentMethodRepository;

    private PaymentMethodGatewayFactoryExtension $extension;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepository::class);
        $this->extension = new PaymentMethodGatewayFactoryExtension($this->paymentMethodRepository);
    }

    public function test_it_provides_the_sylius_has_payment_method_with_gateway_factory_function(): void
    {
        $functions = array_map(fn ($function) => $function->getName(), $this->extension->getFunctions());

        self::assertSame(['sylius_has_payment_method_with_gateway_factory'], $functions);
    }

    public function test_it_returns_true_when_a_payment_method_uses_the_gateway_factory(): void
    {
        $this->expectCount('sylius.paypal', 1);

        self::assertTrue($this->extension->hasPaymentMethodWithGatewayFactory('sylius.paypal'));
    }

    public function test_it_returns_false_when_no_payment_method_uses_the_gateway_factory(): void
    {
        $this->expectCount('sylius.paypal', 0);

        self::assertFalse($this->extension->hasPaymentMethodWithGatewayFactory('sylius.paypal'));
    }

    private function expectCount(string $factoryName, int $count): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn($count);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::once())->method('select')->with('COUNT(o.id)')->willReturnSelf();
        $queryBuilder->expects(self::once())->method('innerJoin')->with('o.gatewayConfig', 'gatewayConfig')->willReturnSelf();
        $queryBuilder->expects(self::once())->method('andWhere')->with('gatewayConfig.factoryName = :factoryName')->willReturnSelf();
        $queryBuilder->expects(self::once())->method('setParameter')->with('factoryName', $factoryName)->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $this->paymentMethodRepository->expects(self::once())->method('createQueryBuilder')->with('o')->willReturn($queryBuilder);
    }
}
