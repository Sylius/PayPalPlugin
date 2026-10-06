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
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Manager\PayPalCredentialsManagerInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;

final readonly class PayPalSandboxPaymentMethodCreator implements PayPalSandboxPaymentMethodCreatorInterface
{
    public function __construct(
        private FactoryInterface $gatewayFactory,
        private FactoryInterface $paymentMethodFactory,
        private EntityManagerInterface $entityManager,
        private PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider,
        private PayPalCredentialsManagerInterface $credentialsManager,
    ) {
    }

    public function create(string $clientId, string $clientSecret, string $merchantId): PaymentMethodInterface
    {
        $credentials = [
            PayPalGatewayConfig::CLIENT_ID => $clientId,
            PayPalGatewayConfig::CLIENT_SECRET => $clientSecret,
            PayPalGatewayConfig::MERCHANT_ID => $merchantId,
            PayPalGatewayConfig::SYLIUS_MERCHANT_ID => self::SYLIUS_SANDBOX_MERCHANT_ID,
            PayPalGatewayConfig::PARTNER_ATTRIBUTION_ID => SyliusPayPalExtension::PARTNER_ATTRIBUTION_ID,
        ];

        if ($this->payPalPaymentMethodProvider->exists()) {
            $paymentMethod = $this->payPalPaymentMethodProvider->provide();
            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $paymentMethod->getGatewayConfig();
            $gatewayConfig->setConfig(
                $this->credentialsManager->store($gatewayConfig->getConfig(), true, $credentials),
            );
            $paymentMethod->setEnabled(true);

            $this->entityManager->flush();

            return $paymentMethod;
        }

        $gatewayConfig = $this->createGatewayConfig($credentials);
        $paymentMethod = $this->createPaymentMethod($gatewayConfig);

        $this->entityManager->persist($paymentMethod);
        $this->entityManager->flush();

        return $paymentMethod;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function createGatewayConfig(array $credentials): GatewayConfigInterface
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $this->gatewayFactory->createNew();
        $gatewayConfig->setFactoryName(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $gatewayConfig->setGatewayName(self::GATEWAY_NAME);

        $gatewayConfig->setConfig($this->credentialsManager->store([
            PayPalGatewayConfig::USE_AUTHORIZE => 1,
            PayPalGatewayConfig::REPORTS_SFTP_PASSWORD => null,
            PayPalGatewayConfig::REPORTS_SFTP_USERNAME => null,
        ], true, $credentials));

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
