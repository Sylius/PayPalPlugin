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

namespace Tests\Sylius\PayPalPlugin\Unit\Onboarding\Manager;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Onboarding\Manager\SellerNonceManager;
use Sylius\PayPalPlugin\Onboarding\Manager\SellerNonceManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class SellerNonceManagerTest extends TestCase
{
    private SellerNonceManager $sellerNonceManager;

    protected function setUp(): void
    {
        parent::setUp();

        $requestStack = new RequestStack();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack->push($request);

        $this->sellerNonceManager = new SellerNonceManager($requestStack);
    }

    #[Test]
    public function it_implements_seller_nonce_manager_interface(): void
    {
        self::assertInstanceOf(SellerNonceManagerInterface::class, $this->sellerNonceManager);
    }

    #[Test]
    public function it_generates_a_nonce_of_at_least_forty_characters(): void
    {
        $nonce = $this->sellerNonceManager->generate();

        self::assertGreaterThanOrEqual(40, strlen($nonce));
    }

    #[Test]
    public function it_generates_a_different_nonce_on_every_call(): void
    {
        self::assertNotSame($this->sellerNonceManager->generate(), $this->sellerNonceManager->generate());
    }

    #[Test]
    public function it_returns_the_generated_nonce_without_removing_it(): void
    {
        $nonce = $this->sellerNonceManager->generate();

        self::assertSame($nonce, $this->sellerNonceManager->get());
        self::assertSame($nonce, $this->sellerNonceManager->get());
    }

    #[Test]
    public function it_removes_the_stored_nonce(): void
    {
        $this->sellerNonceManager->generate();

        $this->sellerNonceManager->remove();

        self::assertNull($this->sellerNonceManager->get());
    }

    #[Test]
    public function it_returns_null_when_nothing_was_generated(): void
    {
        self::assertNull($this->sellerNonceManager->get());
    }
}
