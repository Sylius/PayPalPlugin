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
use Sylius\PayPalPlugin\Manager\CredentialsManagerInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\SellerOnboardingResult;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;
use Sylius\PayPalPlugin\Remover\CachedAccessTokenRemoverInterface;

final readonly class PayPalOnboardingPaymentMethodCreator implements PayPalOnboardingPaymentMethodCreatorInterface
{
    public function __construct(
        private FactoryInterface $gatewayFactory,
        private FactoryInterface $paymentMethodFactory,
        private EntityManagerInterface $entityManager,
        private string $partnerAttributionId,
        private PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider,
        private CredentialsManagerInterface $credentialsManager,
        private CachedAccessTokenRemoverInterface $cachedAccessTokenRemover,
    ) {
    }

    public function create(SellerOnboardingResult $result, bool $sandbox = false): PaymentMethodInterface
    {
        $credentials = [
            PayPalGatewayConfig::CLIENT_ID => $result->getClientId(),
            PayPalGatewayConfig::CLIENT_SECRET => $result->getClientSecret(),
            PayPalGatewayConfig::MERCHANT_ID => $result->getMerchantId(),
            PayPalGatewayConfig::SYLIUS_MERCHANT_ID => $result->getMerchantId(),
            PayPalGatewayConfig::PARTNER_ATTRIBUTION_ID => $this->partnerAttributionId,
        ];

        $paymentMethod = $this->upsertPaymentMethod($credentials, $sandbox);
        $paymentMethod->setEnabled($result->getStatus()->isComplete());

        return $paymentMethod;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function upsertPaymentMethod(array $credentials, bool $sandbox): PaymentMethodInterface
    {
        if ($this->payPalPaymentMethodProvider->exists()) {
            $paymentMethod = $this->payPalPaymentMethodProvider->provide();
            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $paymentMethod->getGatewayConfig();
            $gatewayConfig->setConfig(
                $this->credentialsManager->store($gatewayConfig->getConfig(), $sandbox, $credentials),
            );
            $this->cachedAccessTokenRemover->remove($paymentMethod);

            return $paymentMethod;
        }

        $paymentMethod = $this->createPaymentMethod($this->createGatewayConfig($credentials, $sandbox));

        $this->entityManager->persist($paymentMethod);

        return $paymentMethod;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function createGatewayConfig(array $credentials, bool $sandbox): GatewayConfigInterface
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $this->gatewayFactory->createNew();
        $gatewayConfig->setFactoryName(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $gatewayConfig->setGatewayName(self::GATEWAY_NAME);

        $gatewayConfig->setConfig($this->credentialsManager->store([
            PayPalGatewayConfig::REPORTS_SFTP_PASSWORD => null,
            PayPalGatewayConfig::REPORTS_SFTP_USERNAME => null,
        ], $sandbox, $credentials));

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
