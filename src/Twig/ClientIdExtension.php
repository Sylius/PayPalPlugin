<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Twig;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\PayPalPlugin\Provider\OnboardedPayPalClientIdProviderInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ClientIdExtension extends AbstractExtension
{
    /** @var OnboardedPayPalClientIdProviderInterface */
    private $onboardedPayPalClientIdProvider;

    public function __construct(OnboardedPayPalClientIdProviderInterface $onboardedPayPalClientIdProvider)
    {
        $this->onboardedPayPalClientIdProvider = $onboardedPayPalClientIdProvider;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_pay_pal_client_id', [$this, 'getPayPalClientId']),
        ];
    }

    public function getPayPalClientId(ChannelInterface $channel): string
    {
        return $this->onboardedPayPalClientIdProvider->getForChannel($channel);
    }
}
