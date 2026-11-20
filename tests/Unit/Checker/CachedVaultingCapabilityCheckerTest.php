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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Checker\CachedVaultingCapabilityChecker;
use Sylius\PayPalPlugin\Checker\VaultingCapabilityCheckerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class CachedVaultingCapabilityCheckerTest extends TestCase
{
    private VaultingCapabilityCheckerInterface&MockObject $innerChecker;

    private LoggerInterface&MockObject $logger;

    private CachedVaultingCapabilityChecker $checker;

    protected function setUp(): void
    {
        $this->innerChecker = $this->createMock(VaultingCapabilityCheckerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->checker = new CachedVaultingCapabilityChecker($this->innerChecker, new ArrayAdapter(), $this->logger, 3600, 300);
    }

    #[DataProvider('provideAnswers')]
    public function test_it_asks_paypal_about_a_payment_method_once_and_remembers_the_answer(bool $vaultingActive): void
    {
        $paymentMethod = $this->paymentMethod('PAYPAL', 'MERCHANT-ID', false);
        $this->innerChecker->expects(self::once())->method('isVaultingActive')->with($paymentMethod)->willReturn($vaultingActive);

        self::assertSame($vaultingActive, $this->checker->isVaultingActive($paymentMethod));
        self::assertSame($vaultingActive, $this->checker->isVaultingActive($paymentMethod));
    }

    /** @return iterable<string, array{bool}> */
    public static function provideAnswers(): iterable
    {
        yield 'vaulting active' => [true];
        yield 'vaulting not active' => [false];
    }

    public function test_it_treats_a_payment_method_paypal_could_not_tell_about_as_not_vaulting_without_asking_again(): void
    {
        $paymentMethod = $this->paymentMethod('PAYPAL', 'MERCHANT-ID', false);
        $this->innerChecker->expects(self::once())->method('isVaultingActive')->willThrowException(new \RuntimeException('PayPal is down'));
        $this->logger->expects(self::once())->method('warning');

        self::assertFalse($this->checker->isVaultingActive($paymentMethod));
        self::assertFalse($this->checker->isVaultingActive($paymentMethod));
    }

    public function test_it_remembers_the_answer_of_every_payment_method_merchant_and_mode_apart(): void
    {
        $answers = [
            'PAYPAL' => true,
            'PAYPAL_EU' => false,
            'OTHER-MERCHANT' => false,
            'SANDBOX' => false,
        ];
        $paymentMethods = [
            'PAYPAL' => $this->paymentMethod('PAYPAL', 'MERCHANT-ID', false),
            'PAYPAL_EU' => $this->paymentMethod('PAYPAL_EU', 'MERCHANT-ID', false),
            'OTHER-MERCHANT' => $this->paymentMethod('PAYPAL', 'OTHER-MERCHANT-ID', false),
            'SANDBOX' => $this->paymentMethod('PAYPAL', 'MERCHANT-ID', true),
        ];
        $this->innerChecker
            ->expects(self::exactly(4))
            ->method('isVaultingActive')
            ->willReturnCallback(static fn (PaymentMethod $paymentMethod): bool => $answers[array_search($paymentMethod, $paymentMethods, true)])
        ;

        foreach ($paymentMethods as $name => $paymentMethod) {
            self::assertSame($answers[$name], $this->checker->isVaultingActive($paymentMethod));
            self::assertSame($answers[$name], $this->checker->isVaultingActive($paymentMethod));
        }
    }

    public function test_it_remembers_that_vaulting_is_active_for_the_cooldown(): void
    {
        $this->innerChecker->method('isVaultingActive')->willReturn(true);

        self::assertSame([3600], $this->cachedLifetimes());
    }

    public function test_it_asks_again_soon_when_vaulting_is_not_active_yet(): void
    {
        $this->innerChecker->method('isVaultingActive')->willReturn(false);

        self::assertSame([300], $this->cachedLifetimes());
    }

    public function test_it_asks_again_soon_when_paypal_could_not_tell(): void
    {
        $this->innerChecker->method('isVaultingActive')->willThrowException(new \RuntimeException('PayPal is down'));

        self::assertSame([300], $this->cachedLifetimes());
    }

    /** @return list<mixed> */
    private function cachedLifetimes(): array
    {
        $lifetimes = [];
        $item = $this->createStub(ItemInterface::class);
        $item->method('expiresAfter')->willReturnCallback(static function (mixed $time) use (&$lifetimes, $item): ItemInterface {
            $lifetimes[] = $time;

            return $item;
        });

        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnCallback(static fn (string $key, callable $callback): mixed => $callback($item));

        (new CachedVaultingCapabilityChecker($this->innerChecker, $cache, $this->logger, 3600, 300))->isVaultingActive($this->paymentMethod('PAYPAL', 'MERCHANT-ID', false));

        return $lifetimes;
    }

    private function paymentMethod(string $code, string $merchantId, bool $sandbox): PaymentMethod
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('sylius_paypal');
        $gatewayConfig->setConfig(['merchant_id' => $merchantId, 'sandbox' => $sandbox]);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setCode($code);
        $paymentMethod->setGatewayConfig($gatewayConfig);

        return $paymentMethod;
    }
}
