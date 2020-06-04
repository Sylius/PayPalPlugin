<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin;

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SandboxOnboardingInitiator implements OnboardingInitiatorInterface
{
    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /** @var HttpClientInterface */
    private $httpClient;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $payPalClientId;

    /** @var string */
    private $payPalSecret;

    /** @var string */
    private $payPalTrackingId;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $payPalClientId,
        string $payPalSecret,
        string $payPalTrackingId
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->payPalClientId = $payPalClientId;
        $this->payPalSecret = $payPalSecret;
        $this->payPalTrackingId = $payPalTrackingId;
    }

    public function initiate(PaymentMethodInterface $paymentMethod): string
    {
        if (!$this->supports($paymentMethod)) {
            throw new \DomainException('not supported'); // TODO: Lol, improve this message
        }

        $accessToken = $this->getAccessToken();

        return $this->getRedirectUrl($accessToken);
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

        if (isset($gatewayConfig->getConfig()['merchant_id'])) {
            return false;
        }

        return true;
    }

    private function getAccessToken(): string
    {
        $response = $this->httpClient->request('POST', 'https://api.sandbox.paypal.com/v1/oauth2/token', [
            'auth_basic' => [$this->payPalClientId, $this->payPalSecret], // TODO: I've got a feeling those shouldn't be shared with merchants :shrug:
            'body' => ['grant_type' => 'client_credentials'],
        ]);

        $this->logger->debug($response->getContent(false)); // TODO: We should probably remove it before releasing the plugin

        /**
         * @psalm-var array{access_token: string} $arrayResponse
         */
        $arrayResponse = $response->toArray();

        return $arrayResponse['access_token'];
    }

    private function getRedirectUrl(string $accessToken): string
    {
        $response = $this->httpClient->request('POST', 'https://api.sandbox.paypal.com/v2/customer/partner-referrals', [
            'auth_bearer' => $accessToken,
            'json' => [
                'email' => 'sb-nevei1350290@business.example.com', // TODO: Take it from the current admin account (or let's use the test business account for now)
                'preferred_language_code' => 'en-US', // TODO: Take it from the locale context
                'tracking_id' => $this->payPalTrackingId,
                'partner_config_override' => [
                    'partner_logo_url' => 'https://demo.sylius.com/assets/shop/img/logo.png', // TODO: Make sure this logo url will never change
                    'return_url' => $this->urlGenerator->generate(
                        'sylius_admin_payment_method_create',
                        ['factory' => 'sylius.pay_pal'],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ),
                ],
                'operations' => [
                    ['operation' => 'API_INTEGRATION'], // TODO: Make sure we want THIS operation and no other ones
                ],
                'products' => [
                    'PPCP', // TODO: Make sure we want just this one product
                ],
            ],
        ]);

        $this->logger->debug($response->getContent(false)); // TODO: We should probably remove it before releasing the plugin

        /**
         * @psalm-var array{links: list<array{href: string, rel: string}>} $arrayResponse
         */
        $arrayResponse = $response->toArray();

        foreach ($arrayResponse['links'] as $link) {
            if ($link['rel'] === 'action_url') {
                return $link['href'];
            }
        }

        throw new \RuntimeException('Could not find action URL in the response!');
    }
}
