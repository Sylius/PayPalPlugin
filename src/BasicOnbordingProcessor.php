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

        /**
         * TODO: Remove this snippet before releasing the package
         *
         * https://sylius.local:8080/admin/payment-methods/new/sylius.pay_pal?merchantId=sylius-ppcp4p-bn-code&merchantIdInPayPal=JQVY284FYA5PC&permissionsGranted=false&accountStatus=BUSINESS_ACCOUNT&consentStatus=false&productIntentID=addipmt&productIntentId=addipmt&isEmailConfirmed=true&returnMessage=To%20start%20accepting%20payments,%20please%20log%20in%20to%20PayPal%20and%20finish%20signing%20up.
         */
        $gatewayConfig->setConfig([
            'merchant_id' => $request->query->get('merchantId'),
            'merchant_id_in_paypal' => $request->query->get('merchantIdInPayPal'),
            'permissions_granted' => $request->query->get('permissionsGranted') === 'true',
            'account_status' => $request->query->get('accountStatus'),
            'consent_status' => $request->query->get('consentStatus') === 'true',
            'product_intent_id' => $request->query->get('productIntentID'),
            'email_confirmed' => $request->query->get('isEmailConfirmed') === 'true',
            'return_message' => $request->query->get('returnMessage'),
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
