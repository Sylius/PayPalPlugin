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
use Sylius\PayPalPlugin\Exception\OnboardingFailedException;
use Sylius\PayPalPlugin\Exception\OnboardingSessionExpiredException;
use Sylius\PayPalPlugin\Exception\PayPalPaymentMethodAlreadyExistsException;
use Sylius\PayPalPlugin\Onboarding\Processor\OnboardingCompletionProcessorInterface;
use Sylius\PayPalPlugin\Provider\OnboardingStatusMessagesProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final readonly class CompleteOnboardingAction
{
    public function __construct(
        private OnboardingCompletionProcessorInterface $onboardingCompletionProcessor,
        private OnboardingStatusMessagesProviderInterface $onboardingStatusMessagesProvider,
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
            Assert::stringNotEmpty($data['authCode']);
            Assert::stringNotEmpty($data['sharedId']);
        } catch (\JsonException | \InvalidArgumentException) {
            return new JsonResponse(['redirectUrl' => $indexUrl], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->onboardingCompletionProcessor->process($data['authCode'], $data['sharedId']);
        } catch (PayPalPaymentMethodAlreadyExistsException) {
            $flashBag->add('error', 'sylius_paypal.more_than_one_seller_not_allowed');

            return new JsonResponse(['redirectUrl' => $indexUrl], Response::HTTP_BAD_REQUEST);
        } catch (OnboardingSessionExpiredException) {
            $flashBag->add('error', 'sylius_paypal.onboarding_session_expired');

            return new JsonResponse(['redirectUrl' => $indexUrl], Response::HTTP_BAD_REQUEST);
        } catch (OnboardingFailedException $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            $flashBag->add('error', 'sylius_paypal.could_not_create_paypal_payment_method');

            return new JsonResponse(['redirectUrl' => $indexUrl], Response::HTTP_BAD_REQUEST);
        }

        if ($result->getPaymentMethod()->isEnabled()) {
            $flashBag->add('success', 'sylius_paypal.production_connected_successfully');
        }

        foreach ($this->onboardingStatusMessagesProvider->provide($result->getStatus()) as $message) {
            $flashBag->add('warning', $message);
        }

        if (!$result->isWebhookUrlValid()) {
            $flashBag->add('warning', 'sylius_paypal.webhook_url_not_valid');
        }

        return new JsonResponse([
            'redirectUrl' => $this->urlGenerator->generate('sylius_admin_payment_method_update', ['id' => $result->getPaymentMethod()->getId()]),
        ]);
    }
}
