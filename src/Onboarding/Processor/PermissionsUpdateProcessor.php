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

namespace Sylius\PayPalPlugin\Onboarding\Processor;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\PayPalPlugin\Checker\PaymentSourceCapabilityChecker;
use Sylius\PayPalPlugin\Checker\PaymentSourceCapabilityCheckerInterface;
use Sylius\PayPalPlugin\Exception\OnboardingAccountMismatchException;
use Sylius\PayPalPlugin\Exception\OnboardingFailedException;
use Sylius\PayPalPlugin\Exception\OnboardingSessionExpiredException;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Onboarding\Manager\SellerNonceManagerInterface;
use Sylius\PayPalPlugin\Onboarding\Resolver\SellerOnboardingResolverInterface;
use Sylius\PayPalPlugin\Provider\MerchantIntegrationStatusCacheInterface;

final readonly class PermissionsUpdateProcessor implements PermissionsUpdateProcessorInterface
{
    public function __construct(
        private SellerNonceManagerInterface $sellerNonceManager,
        private SellerOnboardingResolverInterface $sellerOnboardingResolver,
        private MerchantIntegrationStatusCacheInterface $merchantIntegrationStatusCache,
        private PaymentSourceCapabilityCheckerInterface $paymentSourceCapabilityChecker,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(PaymentMethodInterface $paymentMethod, string $authCode, string $sharedId): OnboardingStatus
    {
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig);
        $sandbox = $config->isSandbox();

        $sellerNonce = $this->sellerNonceManager->get($sandbox);
        if (null === $sellerNonce) {
            throw new OnboardingSessionExpiredException();
        }

        try {
            $sellerOnboardingResult = $this->sellerOnboardingResolver->resolve($authCode, $sharedId, $sellerNonce, $sandbox);
        } catch (\Throwable $exception) {
            throw new OnboardingFailedException($exception);
        }

        if ($sellerOnboardingResult->getMerchantId() !== $config->merchantId()) {
            throw new OnboardingAccountMismatchException($config->merchantId(), $sellerOnboardingResult->getMerchantId());
        }

        $this->merchantIntegrationStatusCache->forget($paymentMethod);
        $this->turnOffTogglesWithoutCapability($paymentMethod, $gatewayConfig);

        $this->entityManager->flush();
        $this->sellerNonceManager->remove($sandbox);

        return $sellerOnboardingResult->getStatus();
    }

    private function turnOffTogglesWithoutCapability(PaymentMethodInterface $paymentMethod, GatewayConfigInterface $gatewayConfig): void
    {
        $config = $gatewayConfig->getConfig();
        foreach (PaymentSourceCapabilityChecker::TOGGLES as $field => $paymentSource) {
            if (true === ($config[$field] ?? false) && !$this->paymentSourceCapabilityChecker->isAvailable($paymentSource, $paymentMethod)) {
                $config[$field] = false;
            }
        }

        $gatewayConfig->setConfig($config);
    }
}
