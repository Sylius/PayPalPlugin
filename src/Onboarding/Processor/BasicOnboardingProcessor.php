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

namespace Sylius\PayPalPlugin\Onboarding\Processor;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookAlreadyRegisteredException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookUrlNotValidException;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

final readonly class BasicOnboardingProcessor implements OnboardingProcessorInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private SellerWebhookRegistrarInterface $sellerWebhookRegistrar,
        private string $url,
        private RequestFactoryInterface $requestFactory,
    ) {
    }

    public function process(
        PaymentMethodInterface $paymentMethod,
        Request $request,
    ): PaymentMethodInterface {
        if (!$this->supports($paymentMethod, $request)) {
            throw new \DomainException('not supported');
        }

        $gatewayConfig = $paymentMethod->getGatewayConfig();
        Assert::notNull($gatewayConfig);

        $onboardingId = (string) $request->query->get('onboarding_id');

        $checkPartnerReferralsRequest = $this->requestFactory->createRequest(
            'GET',
            sprintf('%s/partner-referrals/check/%s', $this->url, $onboardingId),
        )
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json');

        $checkPartnerReferralsResponse = $this->httpClient->sendRequest($checkPartnerReferralsRequest);
        $response = (array) json_decode($checkPartnerReferralsResponse->getBody()->getContents(), true);

        if (!isset($response['client_id']) || !isset($response['client_secret'])) {
            throw new PayPalPluginException();
        }

        $gatewayConfig->setConfig([
            PayPalGatewayConfig::CLIENT_ID => $response['client_id'],
            PayPalGatewayConfig::CLIENT_SECRET => $response['client_secret'],
            PayPalGatewayConfig::MERCHANT_ID => $response['merchant_id'],
            PayPalGatewayConfig::SYLIUS_MERCHANT_ID => $response['sylius_merchant_id'],
            PayPalGatewayConfig::ONBOARDING_ID => $onboardingId,
            PayPalGatewayConfig::PARTNER_ATTRIBUTION_ID => $response['partner_attribution_id'],
        ]);

        $permissionsGranted = $request->query->get('permissionsGranted') === null || (bool) $request->query->get('permissionsGranted');
        if (!$permissionsGranted) {
            $paymentMethod->setEnabled(false);
        }

        try {
            $this->sellerWebhookRegistrar->register($paymentMethod);
        } catch (PayPalWebhookUrlNotValidException) {
            $paymentMethod->setEnabled(false);
        } catch (PayPalWebhookAlreadyRegisteredException) {
            $paymentMethod->setEnabled(true);
        }

        return $paymentMethod;
    }

    public function supports(PaymentMethodInterface $paymentMethod, Request $request): bool
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();

        if ($gatewayConfig === null) {
            return false;
        }

        if ($gatewayConfig->getFactoryName() !== SyliusPayPalExtension::PAYPAL_FACTORY_NAME) {
            return false;
        }

        return $request->query->has('onboarding_id');
    }
}
