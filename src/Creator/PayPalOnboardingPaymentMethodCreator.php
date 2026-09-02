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
use Sylius\PayPalPlugin\Exception\PayPalWebhookAlreadyRegisteredException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookUrlNotValidException;
use Sylius\PayPalPlugin\Model\SellerOnboardingResult;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;

final readonly class PayPalOnboardingPaymentMethodCreator implements PayPalOnboardingPaymentMethodCreatorInterface
{
    public function __construct(
        private SellerWebhookRegistrarInterface $sellerWebhookRegistrar,
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

        try {
            $this->sellerWebhookRegistrar->register($paymentMethod);
        } catch (PayPalWebhookUrlNotValidException) {
            $paymentMethod->setEnabled(false);
        } catch (PayPalWebhookAlreadyRegisteredException) {
            // webhook already exists from a previous attempt; keep the onboarding-status decision above
        }

        $this->entityManager->persist($paymentMethod);
        $this->entityManager->flush();

        return $paymentMethod;
    }

    private function createGatewayConfig(SellerOnboardingResult $result): GatewayConfigInterface
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $this->gatewayFactory->createNew();
        $gatewayConfig->setFactoryName(SyliusPayPalExtension::PAYPAL_FACTORY_NAME);
        $gatewayConfig->setGatewayName(self::GATEWAY_NAME);

        $gatewayConfig->setConfig([
            'client_id' => $result->getClientId(),
            'client_secret' => $result->getClientSecret(),
            'merchant_id' => $result->getMerchantId(),
            'use_authorize' => 1,
            'sylius_merchant_id' => $result->getMerchantId(),
            'reports_sftp_password' => null,
            'reports_sftp_username' => null,
            'partner_attribution_id' => $this->partnerAttributionId,
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
