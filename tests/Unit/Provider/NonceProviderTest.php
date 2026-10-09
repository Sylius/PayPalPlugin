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

use PHPUnit\Framework\TestCase;
use Sylius\PayPalPlugin\Provider\NonceProvider;
use Sylius\PayPalPlugin\Provider\NonceProviderInterface;

final class NonceProviderTest extends TestCase
{
    private NonceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new NonceProvider();
    }

    public function test_it_implements_nonce_provider_interface(): void
    {
        self::assertInstanceOf(NonceProviderInterface::class, $this->provider);
    }

    public function test_it_provides_a_nonce_the_redirect_routes_accept(): void
    {
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $this->provider->provide());
    }

    public function test_it_provides_a_different_nonce_for_every_payer_action(): void
    {
        self::assertNotSame($this->provider->provide(), $this->provider->provide());
    }
}
