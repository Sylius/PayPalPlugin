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

namespace Tests\Sylius\PayPalPlugin\Unit\Creator;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\PayPalPlugin\Creator\PayPalSandboxPaymentMethodCreator;
use Sylius\PayPalPlugin\Manager\CredentialsManagerInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;
use Sylius\PayPalPlugin\Remover\CachedAccessTokenRemoverInterface;

final class PayPalSandboxPaymentMethodCreatorTest extends TestCase
{
    public function test_it_creates_and_persists_payment_method_with_gateway_config(): void
    {
        $clientId = 'test_client_id';
        $clientSecret = 'test_client_secret';
        $merchantId = 'test_merchant_id';

        $storedConfig = [
            'use_authorize' => 1,
            'reports_sftp_password' => null,
            'reports_sftp_username' => null,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'merchant_id' => $merchantId,
            'sylius_merchant_id' => 'sandbox_merchant_id',
            'partner_attribution_id' => 'PARTNER-ID',
            'sandbox' => true,
        ];

        /** @var FactoryInterface&MockObject $gatewayFactory */
        $gatewayFactory = $this->createMock(FactoryInterface::class);
        /** @var FactoryInterface&MockObject $paymentMethodFactory */
        $paymentMethodFactory = $this->createMock(FactoryInterface::class);
        /** @var EntityManagerInterface&MockObject $entityManager */
        $entityManager = $this->createMock(EntityManagerInterface::class);
        /** @var PayPalPaymentMethodProviderInterface&MockObject $payPalPaymentMethodProvider */
        $payPalPaymentMethodProvider = $this->createMock(PayPalPaymentMethodProviderInterface::class);
        /** @var CredentialsManagerInterface&MockObject $credentialsManager */
        $credentialsManager = $this->createMock(CredentialsManagerInterface::class);
        /** @var CachedAccessTokenRemoverInterface&MockObject $cachedAccessTokenRemover */
        $cachedAccessTokenRemover = $this->createMock(CachedAccessTokenRemoverInterface::class);
        $cachedAccessTokenRemover->expects(self::never())->method('remove');

        /** @var GatewayConfigInterface&MockObject $gatewayConfig */
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        /** @var PaymentMethodInterface&MockObject $paymentMethod */
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $payPalPaymentMethodProvider->expects(self::once())
            ->method('exists')
            ->willReturn(false);

        $credentialsManager->expects(self::once())
            ->method('store')
            ->with(self::anything(), true, self::anything())
            ->willReturn($storedConfig);

        $gatewayFactory->expects(self::once())
            ->method('createNew')
            ->willReturn($gatewayConfig);

        $gatewayConfig->expects(self::once())
            ->method('setFactoryName')
            ->with('sylius_paypal');
        $gatewayConfig->expects(self::once())
            ->method('setGatewayName')
            ->with('sylius_paypal_sandbox');
        $gatewayConfig->expects(self::once())
            ->method('setConfig')
            ->with($storedConfig);

        $paymentMethodFactory->expects(self::once())
            ->method('createNew')
            ->willReturn($paymentMethod);

        $paymentMethod->expects(self::once())
            ->method('setGatewayConfig')
            ->with($gatewayConfig);
        $paymentMethod->expects(self::once())
            ->method('setCode')
            ->with('PAYPAL');
        $paymentMethod->expects(self::once())
            ->method('setName')
            ->with('PayPal');
        $paymentMethod->expects(self::once())
            ->method('setDescription')
            ->with('Pay with PayPal');

        $entityManager->expects(self::once())
            ->method('persist')
            ->with($paymentMethod);
        $entityManager->expects(self::once())
            ->method('flush');

        $creator = new PayPalSandboxPaymentMethodCreator(
            $gatewayFactory,
            $paymentMethodFactory,
            $entityManager,
            $payPalPaymentMethodProvider,
            $credentialsManager,
            $cachedAccessTokenRemover,
        );

        $result = $creator->create($clientId, $clientSecret, $merchantId);

        self::assertSame($paymentMethod, $result);
    }

    public function test_it_stores_sandbox_credentials_in_the_existing_payment_method(): void
    {
        $existingConfig = ['sandbox' => false, 'client_id' => 'PROD'];
        $storedConfig = ['sandbox' => true, 'client_id' => 'test_client_id'];

        /** @var EntityManagerInterface&MockObject $entityManager */
        $entityManager = $this->createMock(EntityManagerInterface::class);
        /** @var PayPalPaymentMethodProviderInterface&MockObject $payPalPaymentMethodProvider */
        $payPalPaymentMethodProvider = $this->createMock(PayPalPaymentMethodProviderInterface::class);
        /** @var CredentialsManagerInterface&MockObject $credentialsManager */
        $credentialsManager = $this->createMock(CredentialsManagerInterface::class);
        /** @var CachedAccessTokenRemoverInterface&MockObject $cachedAccessTokenRemover */
        $cachedAccessTokenRemover = $this->createMock(CachedAccessTokenRemoverInterface::class);
        /** @var GatewayConfigInterface&MockObject $gatewayConfig */
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        /** @var PaymentMethodInterface&MockObject $paymentMethod */
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $payPalPaymentMethodProvider->method('exists')->willReturn(true);
        $payPalPaymentMethodProvider->method('provide')->willReturn($paymentMethod);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);
        $gatewayConfig->method('getConfig')->willReturn($existingConfig);

        $credentialsManager->expects(self::once())
            ->method('store')
            ->with($existingConfig, true, [
                'client_id' => 'test_client_id',
                'client_secret' => 'test_client_secret',
                'merchant_id' => 'test_merchant_id',
                'sylius_merchant_id' => 'SYLIUS_SANDBOX_MERCHANT_ID',
                'partner_attribution_id' => 'Sylius_MP_PPCP',
            ])
            ->willReturn($storedConfig);
        $gatewayConfig->expects(self::once())->method('setConfig')->with($storedConfig);
        $paymentMethod->expects(self::once())->method('setEnabled')->with(true);
        $cachedAccessTokenRemover->expects(self::once())->method('remove')->with($paymentMethod);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $creator = new PayPalSandboxPaymentMethodCreator(
            $this->createMock(FactoryInterface::class),
            $this->createMock(FactoryInterface::class),
            $entityManager,
            $payPalPaymentMethodProvider,
            $credentialsManager,
            $cachedAccessTokenRemover,
        );

        self::assertSame($paymentMethod, $creator->create('test_client_id', 'test_client_secret', 'test_merchant_id'));
    }
}
