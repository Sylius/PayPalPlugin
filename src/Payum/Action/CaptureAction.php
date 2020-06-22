<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Payum\Action;

use GuzzleHttp\ClientInterface;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Request\Capture;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Payum\Model\PayPalApi;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CaptureAction implements ActionInterface, ApiAwareInterface
{
    /** @var PayPalApi|null */
    private $api;

    /** @var ClientInterface */
    private $httpClient;

    public function __construct(ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /** @param Capture $request */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getModel();
        /** @var PaymentMethodInterface $method */
        $method = $payment->getMethod();
        $gatewayConfig = $method->getGatewayConfig();
        $config = $gatewayConfig->getConfig();

        $response = $this->httpClient->request(
            'POST',
            'https://sylius.local:8001/create-order',
            [
                'verify' => false,
                'json' => [
                    'clientId' => $config['client_id'],
                    'clientSecret' => $config['client_secret'],
                    'currencyCode' => $payment->getOrder()->getCurrencyCode(),
                    'amount' => (string) ($payment->getAmount()/100),
                ]
            ]
        );

        $content = json_decode($response->getBody()->getContents(), true);
        $payment->setDetails(['status' => $content['status'], 'order_id' => $content['id']]);
    }

    public function supports($request): bool
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof PaymentInterface
        ;
    }

    public function setApi($api): void
    {
        if (!$api instanceof PayPalApi) {
            throw new UnsupportedApiException('Not supported');
        }

        $this->api = $api;
    }
}
