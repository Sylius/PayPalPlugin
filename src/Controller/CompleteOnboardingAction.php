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

namespace Sylius\PayPalPlugin\Controller;

use Psr\Log\LoggerInterface;
use Sylius\PayPalPlugin\Creator\PayPalOnboardingPaymentMethodCreatorInterface;
use Sylius\PayPalPlugin\Onboarding\Resolver\SellerOnboardingResolverInterface;
use Sylius\PayPalPlugin\Provider\SellerNonceProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final readonly class CompleteOnboardingAction
{
    public function __construct(
        private SellerOnboardingResolverInterface $sellerOnboardingResolver,
        private PayPalOnboardingPaymentMethodCreatorInterface $onboardingPaymentMethodCreator,
        private SellerNonceProviderInterface $sellerNonceProvider,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var FlashBagInterface $flashBag */
        $flashBag = $request->getSession()->getBag('flashes');
        $indexUrl = $this->urlGenerator->generate('sylius_admin_payment_method_index');

        try {
            $data = (array) json_decode(
                json: $request->getContent(),
                associative: true,
                flags: \JSON_THROW_ON_ERROR,
            );

            Assert::keyExists($data, 'authCode');
            Assert::keyExists($data, 'sharedId');
            Assert::stringNotEmpty((string) $data['authCode']);
            Assert::stringNotEmpty((string) $data['sharedId']);
        } catch (\JsonException | \InvalidArgumentException) {
            return new JsonResponse(['redirectUrl' => $indexUrl], Response::HTTP_BAD_REQUEST);
        }

        $sellerNonce = $this->sellerNonceProvider->get();
        if (null === $sellerNonce) {
            $flashBag->add('error', 'sylius_paypal.onboarding_session_expired');

            return new JsonResponse(['redirectUrl' => $indexUrl], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->sellerOnboardingResolver->resolve((string) $data['authCode'], (string) $data['sharedId'], $sellerNonce);
            $paymentMethod = $this->onboardingPaymentMethodCreator->create($result);
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Could not complete the PayPal onboarding: %s', $exception->getMessage()),
                ['exception' => $exception],
            );
            $flashBag->add('error', 'sylius_paypal.could_not_create_paypal_payment_method');

            return new JsonResponse(['redirectUrl' => $indexUrl], Response::HTTP_BAD_REQUEST);
        }

        $this->sellerNonceProvider->remove();

        if ($paymentMethod->isEnabled()) {
            $flashBag->add('success', 'sylius_paypal.production_connected_successfully');
        } else {
            $flashBag->add('warning', 'sylius_paypal.seller_onboarding_incomplete');
        }

        return new JsonResponse([
            'redirectUrl' => $this->urlGenerator->generate('sylius_admin_payment_method_update', ['id' => $paymentMethod->getId()]),
        ]);
    }
}
