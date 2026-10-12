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
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface as PayumGatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\SellerOnboardingResult;

final readonly class PayPalOnboardingPaymentMethodCreator implements PayPalOnboardingPaymentMethodCreatorInterface
{
    public function __construct(
        private FactoryInterface $gatewayFactory,
        private FactoryInterface $paymentMethodFactory,
        private EntityManagerInterface $entityManager,
        private string $partnerAttributionId,
    ) {
    }

    public function create(SellerOnboardingResult $result): PaymentMethodInterface
    {
        $gatewayConfig = $this->createGatewayConfig($result);
        $paymentMethod = $this->createPaymentMethod($gatewayConfig);

        if (!$result->getStatus()->isComplete()) {
            $paymentMethod->setEnabled(false);
        }

        $this->entityManager->persist($paymentMethod);

        return $paymentMethod;
    }

    private function createGatewayConfig(SellerOnboardingResult $result): GatewayConfigInterface
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $this->gatewayFactory->createNew();
        $gatewayConfig->setFactoryName(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $gatewayConfig->setGatewayName(self::GATEWAY_NAME);

        $gatewayConfig->setConfig([
            PayPalGatewayConfig::CLIENT_ID => $result->getClientId(),
            PayPalGatewayConfig::CLIENT_SECRET => $result->getClientSecret(),
            PayPalGatewayConfig::MERCHANT_ID => $result->getMerchantId(),
            PayPalGatewayConfig::SYLIUS_MERCHANT_ID => $result->getMerchantId(),
            PayPalGatewayConfig::REPORTS_SFTP_PASSWORD => null,
            PayPalGatewayConfig::REPORTS_SFTP_USERNAME => null,
            PayPalGatewayConfig::PARTNER_ATTRIBUTION_ID => $this->partnerAttributionId,
        ]);

        if ($gatewayConfig instanceof PayumGatewayConfigInterface) {
            $gatewayConfig->setUsePayum(false);
        }

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
