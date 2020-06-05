<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Onboarding\Api;

use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PartnerReferralsApi implements PartnerReferralsApiInterface
{
    /** @var HttpClientInterface */
    private $httpClient;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $payPalTrackingId;

    public function __construct(
        HttpClientInterface $httpClient,
        UrlGeneratorInterface $urlGenerator,
        LoggerInterface $logger,
        string $payPalTrackingId
    ) {
        $this->httpClient = $httpClient;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
        $this->payPalTrackingId = $payPalTrackingId;
    }

    public function create(string $accessToken): string
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
