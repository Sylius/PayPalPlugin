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
use Sylius\PayPalPlugin\Exception\OnboardingAccountMismatchException;
use Sylius\PayPalPlugin\Exception\OnboardingFailedException;
use Sylius\PayPalPlugin\Exception\OnboardingSessionExpiredException;
use Sylius\PayPalPlugin\Model\OnboardingScope;
use Sylius\PayPalPlugin\Model\OnboardingStatus;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Onboarding\Manager\SellerNonceManagerInterface;
use Sylius\PayPalPlugin\Onboarding\Resolver\SellerOnboardingResolverInterface;

final readonly class PermissionsUpdateProcessor implements PermissionsUpdateProcessorInterface
{
    public function __construct(
        private SellerNonceManagerInterface $sellerNonceManager,
        private SellerOnboardingResolverInterface $sellerOnboardingResolver,
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

        $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), [
            PayPalGatewayConfig::ONBOARDING_SCOPE => OnboardingScope::current()->toArray(),
        ]));
        $this->entityManager->flush();
        $this->sellerNonceManager->remove($sandbox);

        return $sellerOnboardingResult->getStatus();
    }
}
