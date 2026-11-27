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

namespace Tests\Sylius\PayPalPlugin\Unit\EventListener\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Entity\PayPalCredentials;
use Sylius\PayPalPlugin\Entity\PayPalCredentialsInterface;
use Sylius\PayPalPlugin\EventListener\Doctrine\RemovePayPalCredentialsListener;

final class RemovePayPalCredentialsListenerTest extends TestCase
{
    public function test_it_removes_the_cached_access_tokens_of_a_removed_paypal_payment_method(): void
    {
        $paymentMethod = $this->paymentMethod('sylius_paypal');
        $firstCredentials = $this->createStub(PayPalCredentialsInterface::class);
        $secondCredentials = $this->createStub(PayPalCredentialsInterface::class);
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturnCallback(
            static fn (array $criteria): array => $criteria === ['paymentMethod' => $paymentMethod] ? [$firstCredentials, $secondCredentials] : [],
        );
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(PayPalCredentials::class)->willReturn($repository);

        $removed = [];
        $entityManager->expects(self::exactly(2))->method('remove')->willReturnCallback(
            static function (object $object) use (&$removed): void {
                $removed[] = $object;
            },
        );

        (new RemovePayPalCredentialsListener())->preRemove(new PreRemoveEventArgs($paymentMethod, $entityManager));

        self::assertSame([$firstCredentials, $secondCredentials], $removed);
    }

    public function test_it_leaves_the_tokens_alone_when_a_payment_method_of_another_gateway_is_removed(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getRepository');
        $entityManager->expects(self::never())->method('remove');

        (new RemovePayPalCredentialsListener())->preRemove(new PreRemoveEventArgs($this->paymentMethod('offline'), $entityManager));
    }

    public function test_it_ignores_the_removal_of_anything_but_a_payment_method(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getRepository');
        $entityManager->expects(self::never())->method('remove');

        (new RemovePayPalCredentialsListener())->preRemove(new PreRemoveEventArgs($this->createStub(OrderInterface::class), $entityManager));
    }

    private function paymentMethod(string $factoryName): PaymentMethodInterface
    {
        $gatewayConfig = $this->createStub(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);

        $paymentMethod = $this->createStub(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        return $paymentMethod;
    }
}
