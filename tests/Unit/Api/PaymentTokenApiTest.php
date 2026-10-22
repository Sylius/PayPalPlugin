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

namespace Tests\Sylius\PayPalPlugin\Unit\Api;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\PaymentTokenApi;
use Sylius\PayPalPlugin\Client\PayPalClientInterface;

final class PaymentTokenApiTest extends TestCase
{
    public function test_it_deletes_the_vaulted_payment_method(): void
    {
        $paymentMethod = $this->createStub(PaymentMethodInterface::class);

        $client = $this->createMock(PayPalClientInterface::class);
        $client->expects(self::once())->method('delete')->with($paymentMethod, 'v3/vault/payment-tokens/VAULT%2FID')->willReturn([]);

        (new PaymentTokenApi($client))->delete($paymentMethod, 'VAULT/ID');
    }
}
