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
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\SellerOnboardingResult;

final readonly class PayPalOnboardingPaymentMethodCreator implements PayPalOnboardingPaymentMethodCreatorInterface
{
    /** @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository */
    public function __construct(
        private FactoryInterface $gatewayFactory,
        private FactoryInterface $paymentMethodFactory,
        private EntityManagerInterface $entityManager,
        private string $partnerAttributionId,
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
    ) {
    }

    public function create(SellerOnboardingResult $result, bool $sandbox = false): PaymentMethodInterface
    {
        $paymentMethod = $this->createPaymentMethod($this->createGatewayConfig($result, $sandbox));
        $paymentMethod->setEnabled($result->getStatus()->isComplete());

        $this->entityManager->persist($paymentMethod);

        return $paymentMethod;
    }

    private function createGatewayConfig(SellerOnboardingResult $result, bool $sandbox): GatewayConfigInterface
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $this->gatewayFactory->createNew();
        $gatewayConfig->setFactoryName(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $gatewayConfig->setGatewayName(self::GATEWAY_NAME);
        $gatewayConfig->setConfig([
            PayPalGatewayConfig::CLIENT_ID => $result->getClientId(),
            PayPalGatewayConfig::CLIENT_SECRET => $result->getClientSecret(),
            PayPalGatewayConfig::MERCHANT_ID => $result->getMerchantId(),
            PayPalGatewayConfig::PARTNER_ATTRIBUTION_ID => $this->partnerAttributionId,
            PayPalGatewayConfig::SANDBOX => $sandbox,
            PayPalGatewayConfig::REPORTS_SFTP_PASSWORD => null,
            PayPalGatewayConfig::REPORTS_SFTP_USERNAME => null,
            PayPalGatewayConfig::USE_AUTHORIZE => false,
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
        $suffix = $this->unusedSuffix();
        $paymentMethod->setCode(null === $suffix ? self::PAYMENT_METHOD_CODE : sprintf('%s_%d', self::PAYMENT_METHOD_CODE, $suffix));
        $paymentMethod->setName(null === $suffix ? self::PAYMENT_METHOD_NAME : sprintf('%s %d', self::PAYMENT_METHOD_NAME, $suffix));
        $paymentMethod->setDescription(self::PAYMENT_METHOD_DESCRIPTION);
        $paymentMethod->setPosition(0);

        return $paymentMethod;
    }

    private function unusedSuffix(): ?int
    {
        if (null === $this->paymentMethodRepository->findOneBy(['code' => self::PAYMENT_METHOD_CODE])) {
            return null;
        }

        $suffix = 2;
        while (null !== $this->paymentMethodRepository->findOneBy(['code' => sprintf('%s_%d', self::PAYMENT_METHOD_CODE, $suffix)])) {
            ++$suffix;
        }

        return $suffix;
    }
}
