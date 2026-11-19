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

namespace Tests\Sylius\PayPalPlugin\Unit\CommandProvider;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\PayPalPlugin\Command\ChargeVaultedPaymentRequest;
use Sylius\PayPalPlugin\CommandProvider\ChargeVaultedPaymentRequestCommandProvider;

final class ChargeVaultedPaymentRequestCommandProviderTest extends TestCase
{
    public function test_it_supports_the_charge_vaulted_action_only(): void
    {
        $provider = new ChargeVaultedPaymentRequestCommandProvider();

        self::assertTrue($provider->supports($this->paymentRequest('charge_vaulted')));
        self::assertFalse($provider->supports($this->paymentRequest(PaymentRequestInterface::ACTION_CAPTURE)));
    }

    public function test_it_provides_the_charge_vaulted_command_for_the_request(): void
    {
        $command = (new ChargeVaultedPaymentRequestCommandProvider())->provide($this->paymentRequest('charge_vaulted'));

        self::assertInstanceOf(ChargeVaultedPaymentRequest::class, $command);
        self::assertSame('PAYMENT_REQUEST_HASH', $command->getHash());
    }

    private function paymentRequest(string $action): PaymentRequestInterface
    {
        $paymentRequest = $this->createStub(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn($action);
        $paymentRequest->method('getId')->willReturn('PAYMENT_REQUEST_HASH');

        return $paymentRequest;
    }
}
