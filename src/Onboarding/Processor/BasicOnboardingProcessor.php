<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Onboarding\Processor;

use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Webmozart\Assert\Assert;

final class BasicOnboardingProcessor implements OnboardingProcessorInterface
{
    /** @var HttpClientInterface */
    private $httpClient;

    /** @var string */
    private $url;

    public function __construct(HttpClientInterface $httpClient, string $url)
    {
        $this->httpClient = $httpClient;
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

        $checkPartnerReferralsResponse = $this->httpClient->request('GET',
            sprintf('%s/partner-referrals/check/%s', $this->url, (string) $request->query->get('onboarding_id')),
        );

        /** @var array $response */
        $response = json_decode($checkPartnerReferralsResponse->getContent(), true);

        if (!isset($response['client_id']) || !isset($response['client_secret'])) {
            throw new PayPalPluginException();
        }

        $gatewayConfig->setConfig([
            'client_id' => $response['client_id'],
            'client_secret' => $response['client_secret'],
            'onboarding_id' => $request->query->get('onboarding_id'),
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

        return $request->query->has('onboarding_id');
    }
}
