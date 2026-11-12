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
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Exception\OnboardingAccountMismatchException;
use Sylius\PayPalPlugin\Exception\OnboardingFailedException;
use Sylius\PayPalPlugin\Exception\OnboardingSessionExpiredException;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Onboarding\Processor\PermissionsUpdateProcessorInterface;
use Sylius\PayPalPlugin\Provider\OnboardingStatusMessagesProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final readonly class UpdatePermissionsAction
{
    /** @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository */
    public function __construct(
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private PermissionsUpdateProcessorInterface $permissionsUpdateProcessor,
        private OnboardingStatusMessagesProviderInterface $onboardingStatusMessagesProvider,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $paymentMethod = $this->paymentMethodRepository->find($request->attributes->getInt('id'));
        if (!$paymentMethod instanceof PaymentMethodInterface || !$this->isPayPalPaymentMethod($paymentMethod)) {
            throw new NotFoundHttpException();
        }

        /** @var FlashBagInterface $flashBag */
        $flashBag = $request->getSession()->getBag('flashes');
        $updateUrl = $this->urlGenerator->generate('sylius_admin_payment_method_update', ['id' => $paymentMethod->getId()]);

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
            return new JsonResponse(['redirectUrl' => $updateUrl], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->permissionsUpdateProcessor->process($paymentMethod, $data['authCode'], $data['sharedId']);
        } catch (OnboardingSessionExpiredException) {
            $flashBag->add('error', 'sylius_paypal.onboarding_session_expired');

            return new JsonResponse(['redirectUrl' => $updateUrl], Response::HTTP_BAD_REQUEST);
        } catch (OnboardingAccountMismatchException $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            $flashBag->add('error', 'sylius_paypal.permissions_update_another_account');

            return new JsonResponse(['redirectUrl' => $updateUrl], Response::HTTP_BAD_REQUEST);
        } catch (OnboardingFailedException $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            $flashBag->add('error', 'sylius_paypal.permissions_update_failed');

            return new JsonResponse(['redirectUrl' => $updateUrl], Response::HTTP_BAD_REQUEST);
        }

        $verification = $result->verification();
        if ($verification->isUpToDate()) {
            $flashBag->add('success', 'sylius_paypal.permissions_updated');
        } elseif ($verification->isAwaitingPayPal()) {
            $flashBag->add('warning', [
                'message' => 'sylius_paypal.permissions_awaiting_paypal',
                'parameters' => ['%capabilities%' => implode(', ', $verification->describedPendingCapabilities())],
            ]);
        } else {
            $flashBag->add('warning', [
                'message' => 'sylius_paypal.permissions_not_granted',
                'parameters' => ['%capabilities%' => implode(', ', $verification->capabilitiesToRequest())],
            ]);
        }

        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $sandbox = PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->isSandbox();
        foreach ($this->onboardingStatusMessagesProvider->provide($result->status(), $sandbox) as $message) {
            $flashBag->add('warning', $message);
        }

        return new JsonResponse(['redirectUrl' => $updateUrl, 'completed' => $verification->isUpToDate()]);
    }

    private function isPayPalPaymentMethod(PaymentMethodInterface $paymentMethod): bool
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return
            $gatewayConfig instanceof GatewayConfigInterface &&
            $gatewayConfig->getFactoryName() === SyliusPayPalExtension::PAYPAL_FACTORY_NAME
        ;
    }
}
