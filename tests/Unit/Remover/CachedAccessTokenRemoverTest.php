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

namespace Tests\Sylius\PayPalPlugin\Unit\Remover;

use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Entity\PayPalCredentialsInterface;
use Sylius\PayPalPlugin\Remover\CachedAccessTokenRemover;

final class CachedAccessTokenRemoverTest extends TestCase
{
    private ObjectManager&MockObject $payPalCredentialsManager;

    /** @var ObjectRepository<PayPalCredentialsInterface>&MockObject */
    private ObjectRepository&MockObject $payPalCredentialsRepository;

    private CachedAccessTokenRemover $remover;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payPalCredentialsManager = $this->createMock(ObjectManager::class);
        $this->payPalCredentialsRepository = $this->createMock(ObjectRepository::class);

        $this->remover = new CachedAccessTokenRemover($this->payPalCredentialsManager, $this->payPalCredentialsRepository);
    }

    #[Test]
    public function it_removes_the_cached_access_token_of_the_payment_method(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $payPalCredentials = $this->createMock(PayPalCredentialsInterface::class);

        $this->payPalCredentialsRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['paymentMethod' => $paymentMethod])
            ->willReturn($payPalCredentials);
        $this->payPalCredentialsManager->expects(self::once())->method('remove')->with($payPalCredentials);
        $this->payPalCredentialsManager->expects(self::never())->method('flush');

        $this->remover->remove($paymentMethod);
    }

    #[Test]
    public function it_does_nothing_when_no_access_token_is_cached(): void
    {
        $this->payPalCredentialsRepository->method('findOneBy')->willReturn(null);
        $this->payPalCredentialsManager->expects(self::never())->method('remove');

        $this->remover->remove($this->createMock(PaymentMethodInterface::class));
    }
}
