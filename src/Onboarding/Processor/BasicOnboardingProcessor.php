<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Onboarding\Processor;

use GuzzleHttp\ClientInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\WebhookApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final class BasicOnboardingProcessor implements OnboardingProcessorInterface
{
    /** @var ClientInterface */
    private $httpClient;

    /** @var WebhookApiInterface */
    private $webhookApi;

    /** @var AuthorizeClientApiInterface */
    private $authorizeClientApi;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /** @var string */
    private $url;

    public function __construct(
        ClientInterface $httpClient,
        WebhookApiInterface $webhookApi,
        AuthorizeClientApiInterface $authorizeClientApi,
        UrlGeneratorInterface $urlGenerator,
        string $url
    ) {
        $this->httpClient = $httpClient;
        $this->authorizeClientApi = $authorizeClientApi;
        $this->webhookApi = $webhookApi;
        $this->urlGenerator = $urlGenerator;
        $this->url = $url;
    }

    public function process(
        PaymentMethodInterface $paymentMethod,
        Request $request
    ): PaymentMethodInterface {
        if (!$this->supports($paymentMethod, $request)) {
            throw new \DomainException('not supported');
        }

        $gatewayConfig = $paymentMethod->getGatewayConfig();

        /** @var GatewayConfig $gatewayConfig */
        Assert::notNull($gatewayConfig);

        $onboardingId = (string) $request->query->get('onboarding_id');
        $checkPartnerReferralsResponse = $this->httpClient->request(
            'GET',
            sprintf('%s/partner-referrals/check/%s', $this->url, $onboardingId),
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]
        );

        /** @var array $response */
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

        $permissionsGranted = (bool) $request->query->get('permissionsGranted', true);
        if (!$permissionsGranted) {
            $paymentMethod->setEnabled(false);
        }

        $token = $this->authorizeClientApi->authorize(
            (string) $response['client_id'], (string) $response['client_secret']
        );

        $webhookResponse = $this->webhookApi->register($token, $this->urlGenerator->generate('sylius_paypal_plugin_webhook_refund_order', [], UrlGeneratorInterface::ABSOLUTE_URL));

        if (!array_key_exists('id', $webhookResponse) && $webhookResponse['name'] === 'VALIDATION_ERROR') {
            $paymentMethod->setEnabled(false);
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
