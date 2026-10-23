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
use Sylius\PayPalPlugin\Provider\PayPalOrderCreatedStatusesProvider;
use Sylius\PayPalPlugin\Provider\PayPalOrderCreatedStatusesProviderInterface;

final class PayPalOrderCreatedStatusesProviderTest extends TestCase
{
    private PayPalOrderCreatedStatusesProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new PayPalOrderCreatedStatusesProvider();
    }

    public function test_it_implements_paypal_order_created_statuses_provider_interface(): void
    {
        self::assertInstanceOf(PayPalOrderCreatedStatusesProviderInterface::class, $this->provider);
    }

    public function test_it_names_both_statuses_paypal_answers_a_created_order_with(): void
    {
        self::assertSame(['CREATED', 'PAYER_ACTION_REQUIRED'], $this->provider->provide());
    }
}
