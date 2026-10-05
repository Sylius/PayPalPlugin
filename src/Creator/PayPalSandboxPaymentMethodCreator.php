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

namespace Sylius\PayPalPlugin\Creator;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;

final readonly class PayPalSandboxPaymentMethodCreator implements PayPalSandboxPaymentMethodCreatorInterface
{
    public function __construct(
        private FactoryInterface $gatewayFactory,
        private FactoryInterface $paymentMethodFactory,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(string $clientId, string $clientSecret, string $merchantId): PaymentMethodInterface
    {
        $gatewayConfig = $this->createGatewayConfig($clientId, $clientSecret, $merchantId);
        $paymentMethod = $this->createPaymentMethod($gatewayConfig);

        $this->entityManager->persist($paymentMethod);
        $this->entityManager->flush();

        return $paymentMethod;
    }

    private function createGatewayConfig(string $clientId, string $clientSecret, string $merchantId): GatewayConfigInterface
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $this->gatewayFactory->createNew();
        $gatewayConfig->setFactoryName(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $gatewayConfig->setGatewayName(self::GATEWAY_NAME);

        $gatewayConfig->setConfig([
            PayPalGatewayConfig::CLIENT_ID => $clientId,
            PayPalGatewayConfig::CLIENT_SECRET => $clientSecret,
            PayPalGatewayConfig::MERCHANT_ID => $merchantId,
            PayPalGatewayConfig::USE_AUTHORIZE => 1,
            PayPalGatewayConfig::SYLIUS_MERCHANT_ID => self::SYLIUS_SANDBOX_MERCHANT_ID,
            PayPalGatewayConfig::REPORTS_SFTP_PASSWORD => null,
            PayPalGatewayConfig::REPORTS_SFTP_USERNAME => null,
            PayPalGatewayConfig::PARTNER_ATTRIBUTION_ID => self::PARTNER_ATTRIBUTION_ID,
        ]);

        return $gatewayConfig;
    }

    private function createPaymentMethod(GatewayConfigInterface $gatewayConfig): PaymentMethodInterface
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $this->paymentMethodFactory->createNew();
        $paymentMethod->setGatewayConfig($gatewayConfig);
        $paymentMethod->setCode(self::PAYMENT_METHOD_CODE);
        $paymentMethod->setName(self::PAYMENT_METHOD_NAME);
        $paymentMethod->setDescription(self::PAYMENT_METHOD_DESCRIPTION);

        return $paymentMethod;
    }
}
