<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Onboarding\Processor;

use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

final class BasicOnboardingProcessor implements OnboardingProcessorInterface
{
    public function process(PaymentMethodInterface $paymentMethod, Request $request): PaymentMethodInterface
    {
        if (!$this->supports($paymentMethod, $request)) {
            throw new \DomainException('not supported');
        }

        $gatewayConfig = $paymentMethod->getGatewayConfig();

        /** @var GatewayConfig $gatewayConfig */
        Assert::notNull($gatewayConfig);

        $gatewayConfig->setConfig([
            'client_id' => $request->query->get('client_id'),
            'client_secret' => $request->query->get('client_secret'),
            'request_method' => $request->getMethod(),
        ]);

        return $paymentMethod;
    }

    public function supports(PaymentMethodInterface $paymentMethod, Request $request): bool
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        if ($gatewayConfig === null) {
            return false;
        }

        if ($gatewayConfig->getFactoryName() !== 'sylius.pay_pal') {
            return false;
        }

        return $request->query->has('client_id') && $request->query->has('client_secret');
    }
}
