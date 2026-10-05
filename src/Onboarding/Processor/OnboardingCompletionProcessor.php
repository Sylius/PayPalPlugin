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
use Sylius\PayPalPlugin\Creator\PayPalOnboardingPaymentMethodCreatorInterface;
use Sylius\PayPalPlugin\Exception\OnboardingFailedException;
use Sylius\PayPalPlugin\Exception\OnboardingSessionExpiredException;
use Sylius\PayPalPlugin\Exception\PayPalPaymentMethodAlreadyExistsException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookAlreadyRegisteredException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookUrlNotValidException;
use Sylius\PayPalPlugin\Model\OnboardingCompletionResult;
use Sylius\PayPalPlugin\Onboarding\Resolver\SellerOnboardingResolverInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentMethodProviderInterface;
use Sylius\PayPalPlugin\Provider\SellerNonceProviderInterface;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;

final readonly class OnboardingCompletionProcessor implements OnboardingCompletionProcessorInterface
{
    public function __construct(
        private PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider,
        private SellerNonceProviderInterface $sellerNonceProvider,
        private SellerOnboardingResolverInterface $sellerOnboardingResolver,
        private PayPalOnboardingPaymentMethodCreatorInterface $onboardingPaymentMethodCreator,
        private SellerWebhookRegistrarInterface $sellerWebhookRegistrar,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(string $authCode, string $sharedId): OnboardingCompletionResult
    {
        if ($this->payPalPaymentMethodProvider->exists()) {
            throw new PayPalPaymentMethodAlreadyExistsException();
        }

        $sellerNonce = $this->sellerNonceProvider->get();
        if (null === $sellerNonce) {
            throw new OnboardingSessionExpiredException();
        }

        try {
            $sellerOnboardingResult = $this->sellerOnboardingResolver->resolve($authCode, $sharedId, $sellerNonce);
            $paymentMethod = $this->onboardingPaymentMethodCreator->create($sellerOnboardingResult);
            $webhookUrlValid = $this->registerWebhook($paymentMethod);

            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            throw new OnboardingFailedException($exception);
        }

        $this->sellerNonceProvider->remove();

        return new OnboardingCompletionResult($paymentMethod, $sellerOnboardingResult->getStatus(), $webhookUrlValid);
    }

    private function registerWebhook(PaymentMethodInterface $paymentMethod): bool
    {
        try {
            $this->sellerWebhookRegistrar->register($paymentMethod);
        } catch (PayPalWebhookUrlNotValidException) {
            $paymentMethod->setEnabled(false);

            return false;
        } catch (PayPalWebhookAlreadyRegisteredException) {
        }

        return true;
    }
}
