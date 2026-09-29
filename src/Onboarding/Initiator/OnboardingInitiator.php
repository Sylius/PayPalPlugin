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

namespace Sylius\PayPalPlugin\Onboarding\Initiator;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\UrlUtils;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class OnboardingInitiator implements OnboardingInitiatorInterface
{
    private string $createPartnerReferralsUrl;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
        string $facilitatorUrl,
    ) {
        $this->createPartnerReferralsUrl = $facilitatorUrl . '/partner-referrals/create';
    }

    public function initiate(PaymentMethodInterface $paymentMethod): string
    {
        if (!$this->supports($paymentMethod)) {
            throw new \DomainException('not supported'); // TODO: Lol, improve this message
        }

        /** @var AdminUserInterface $user */
        $user = $this->security->getUser();

        return UrlUtils::appendQueryString(
            $this->createPartnerReferralsUrl,
            http_build_query([
                'email' => $user->getEmail(),
                'return_url' => $this->urlGenerator->generate('sylius_admin_payment_method_create', [
                    'factory' => SyliusPayPalExtension::PAYPAL_FACTORY_NAME,
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ]),
            UrlUtils::APPEND_QUERY_STRING_REPLACE_DUPLICATE,
        );
    }

    public function supports(PaymentMethodInterface $paymentMethod): bool // TODO: Design smell - it looks like this function will be the same no matter the implementation
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        if ($gatewayConfig === null) {
            return false;
        }

        if ($gatewayConfig->getFactoryName() !== SyliusPayPalExtension::PAYPAL_FACTORY_NAME) {
            return false;
        }

        if (isset($gatewayConfig->getConfig()[PayPalGatewayConfig::CLIENT_ID])) {
            return false;
        }

        return true;
    }
}
