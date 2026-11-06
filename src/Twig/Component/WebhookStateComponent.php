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

namespace Sylius\PayPalPlugin\Twig\Component;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\Checker\WebhookStateCheckerInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookNotRegisteredException;
use Sylius\PayPalPlugin\Model\WebhookRegistration;
use Sylius\PayPalPlugin\Model\WebhookState;
use Sylius\PayPalPlugin\Registrar\SellerWebhookEventTypesRegistrarInterface;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

final class WebhookStateComponent
{
    use DefaultActionTrait;

    public const VARIANT_BADGE = 'badge';

    public const VARIANT_ALERT = 'alert';

    #[LiveProp]
    public ?int $paymentMethodId = null;

    #[LiveProp]
    public string $variant = self::VARIANT_BADGE;

    public ?WebhookRegistration $registration = null;

    public bool $updateFailed = false;

    public ?string $updateError = null;

    /** @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository */
    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly WebhookStateCheckerInterface $webhookStateChecker,
        private readonly SellerWebhookEventTypesRegistrarInterface $webhookEventTypesRegistrar,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getState(): ?WebhookState
    {
        $paymentMethod = $this->payPalPaymentMethod();
        if (null === $paymentMethod) {
            return null;
        }

        try {
            return $this->webhookStateChecker->check($paymentMethod);
        } catch (\Throwable $exception) {
            $this->logger->error(sprintf(
                'Could not check the webhook of the "%s" payment method: %s',
                (string) $paymentMethod->getCode(),
                $exception->getMessage(),
            ), ['exception' => $exception]);

            return null;
        }
    }

    #[LiveAction]
    public function updateWebhook(): void
    {
        $paymentMethod = $this->payPalPaymentMethod();
        if (null === $paymentMethod) {
            return;
        }

        try {
            $this->registration = $this->webhookEventTypesRegistrar->register($paymentMethod);
        } catch (PayPalWebhookNotRegisteredException $exception) {
            $this->failUpdate($paymentMethod, $exception, $exception->getDescription());
        } catch (PayPalApiException $exception) {
            $this->failUpdate($paymentMethod, $exception, $exception->getDescription());
        } catch (\Throwable $exception) {
            $this->failUpdate($paymentMethod, $exception, null);
        }
    }

    private function failUpdate(PaymentMethodInterface $paymentMethod, \Throwable $exception, ?string $reason): void
    {
        $this->updateFailed = true;
        $this->updateError = $reason;
        $this->logger->error(sprintf(
            'Could not update the webhook of the "%s" payment method: %s',
            (string) $paymentMethod->getCode(),
            $exception->getMessage(),
        ), ['exception' => $exception]);
    }

    private function payPalPaymentMethod(): ?PaymentMethodInterface
    {
        $paymentMethod = null === $this->paymentMethodId ? null : $this->paymentMethodRepository->find($this->paymentMethodId);
        if (!$paymentMethod instanceof PaymentMethodInterface || SyliusPayPalExtension::PAYPAL_FACTORY_NAME !== $paymentMethod->getGatewayConfig()?->getFactoryName()) {
            return null;
        }

        return $paymentMethod;
    }
}
