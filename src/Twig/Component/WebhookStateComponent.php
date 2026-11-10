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

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\Checker\WebhookStateCheckerInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Model\WebhookState;
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

    /** @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository */
    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly WebhookStateCheckerInterface $webhookStateChecker,
    ) {
    }

    public function getState(): ?WebhookState
    {
        $paymentMethod = null === $this->paymentMethodId ? null : $this->paymentMethodRepository->find($this->paymentMethodId);
        if (!$paymentMethod instanceof PaymentMethodInterface || SyliusPayPalExtension::PAYPAL_FACTORY_NAME !== $paymentMethod->getGatewayConfig()?->getFactoryName()) {
            return null;
        }

        return $this->webhookStateChecker->check($paymentMethod);
    }
}
