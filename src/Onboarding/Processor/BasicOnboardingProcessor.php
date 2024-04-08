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

use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookAlreadyRegisteredException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookUrlNotValidException;
use Sylius\PayPalPlugin\Registrar\SellerWebhookRegistrarInterface;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

final class BasicOnboardingProcessor implements OnboardingProcessorInterface
{
    public function __construct(
        private readonly GuzzleClientInterface|ClientInterface $httpClient,
        private readonly SellerWebhookRegistrarInterface $sellerWebhookRegistrar,
        private readonly string $url,
        private readonly ?RequestFactoryInterface $requestFactory = null,
    ) {
        if ($this->httpClient instanceof GuzzleClientInterface) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                'Passing GuzzleHttp\ClientInterface as a first argument in the constructor is deprecated and will be prohibited in 2.0. Use Psr\Http\Client\ClientInterface instead.',
                self::class,
            );
        }

        if (null === $this->requestFactory) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '1.6',
                'Not passing $requestFactory to %s constructor is deprecated and will be prohibited in 2.0',
                self::class,
            );
        }
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

        if ($this->httpClient instanceof GuzzleClientInterface || null === $this->requestFactory) {
            $checkPartnerReferralsResponse = $this->httpClient->request(
                'GET',
                sprintf('%s/partner-referrals/check/%s', $this->url, $onboardingId),
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                ],
            );
        } else {
            $checkPartnerReferralsRequest = $this->requestFactory->createRequest(
                'GET',
                sprintf('%s/partner-referrals/check/%s', $this->url, $onboardingId),
            )
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Accept', 'application/json');

            $checkPartnerReferralsResponse = $this->httpClient->sendRequest($checkPartnerReferralsRequest);
        }
        $response = (array) json_decode($checkPartnerReferralsResponse->getBody()->getContents(), true);

        if (!isset($response['client_id']) || !isset($response['client_secret'])) {
            throw new PayPalPluginException();
        }

        $gatewayConfig->setConfig([
            'client_id' => $response['client_id'],
            'client_secret' => $response['client_secret'],
            'merchant_id' => $response['merchant_id'],
            'sylius_merchant_id' => $response['sylius_merchant_id'],
            'onboarding_id' => $onboardingId,
            'partner_attribution_id' => $response['partner_attribution_id'],
        ]);

        $permissionsGranted = $request->query->get('permissionsGranted') === null ? true : (bool) $request->query->get('permissionsGranted');
        if (!$permissionsGranted) {
            $paymentMethod->setEnabled(false);
        }

        try {
            $this->sellerWebhookRegistrar->register($paymentMethod);
        } catch (PayPalWebhookUrlNotValidException $exception) {
            $paymentMethod->setEnabled(false);
        } catch (PayPalWebhookAlreadyRegisteredException $exception) {
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

        if ($gatewayConfig->getFactoryName() !== 'sylius.pay_pal') {
            return false;
        }

        return $request->query->has('onboarding_id');
    }
}
