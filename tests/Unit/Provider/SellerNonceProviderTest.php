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

namespace Tests\Sylius\PayPalPlugin\Unit\Provider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Provider\SellerNonceProvider;
use Sylius\PayPalPlugin\Provider\SellerNonceProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class SellerNonceProviderTest extends TestCase
{
    private SellerNonceProvider $sellerNonceProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $requestStack = new RequestStack();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack->push($request);

        $this->sellerNonceProvider = new SellerNonceProvider($requestStack);
    }

    #[Test]
    public function it_implements_seller_nonce_provider_interface(): void
    {
        self::assertInstanceOf(SellerNonceProviderInterface::class, $this->sellerNonceProvider);
    }

    #[Test]
    public function it_generates_a_nonce_of_at_least_forty_characters(): void
    {
        $nonce = $this->sellerNonceProvider->generate();

        self::assertGreaterThanOrEqual(40, strlen($nonce));
    }

    #[Test]
    public function it_generates_a_different_nonce_on_every_call(): void
    {
        self::assertNotSame($this->sellerNonceProvider->generate(), $this->sellerNonceProvider->generate());
    }

    #[Test]
    public function it_consumes_the_generated_nonce_once(): void
    {
        $nonce = $this->sellerNonceProvider->generate();

        self::assertSame($nonce, $this->sellerNonceProvider->consume());
        self::assertNull($this->sellerNonceProvider->consume());
    }

    #[Test]
    public function it_returns_null_when_nothing_was_generated(): void
    {
        self::assertNull($this->sellerNonceProvider->consume());
    }
}
