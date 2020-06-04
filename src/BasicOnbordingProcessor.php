<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin;

use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

final class BasicOnbordingProcessor implements OnboardingProcessorInterface
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
            'merchant_id' => $request->query->get('merchantId'),
            'merchant_id_in_paypal' => $request->query->get('merchantIdInPayPal'),
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

        return $request->query->has('merchantId');
    }
}
