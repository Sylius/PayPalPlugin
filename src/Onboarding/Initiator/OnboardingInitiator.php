<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Onboarding\Initiator;

use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;

final class OnboardingInitiator implements OnboardingInitiatorInterface
{
    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /** @var string */
    private $facilitatorUrl;

    /** @var Security */
    private $security;

    public function __construct(UrlGeneratorInterface $urlGenerator, Security $security, string $facilitatorUrl)
    {
        $this->urlGenerator = $urlGenerator;
        $this->security = $security;
        $this->facilitatorUrl = $facilitatorUrl;
    }

    public function initiate(PaymentMethodInterface $paymentMethod): string
    {
        if (!$this->supports($paymentMethod)) {
            throw new \DomainException('not supported'); // TODO: Lol, improve this message
        }

        /** @var AdminUserInterface $user */
        $user = $this->security->getUser();

        return append_query_string(
            $this->facilitatorUrl.'/partner-referrals/create',
            http_build_query([
                'email' => $user->getEmail(),
                'return_url' => $this->urlGenerator->generate('sylius_admin_payment_method_create', [
                    'factory' => 'sylius.pay_pal',
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ]),
            APPEND_QUERY_STRING_REPLACE_DUPLICATE
        );
    }

    public function supports(PaymentMethodInterface $paymentMethod): bool // TODO: Design smell - it looks like this function will be the same no matter the implementation
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        if ($gatewayConfig === null) {
            return false;
        }

        if ($gatewayConfig->getFactoryName() !== 'sylius.pay_pal') {
            return false;
        }

        if (isset($gatewayConfig->getConfig()['client_id'])) {
            return false;
        }

        return true;
    }
}
