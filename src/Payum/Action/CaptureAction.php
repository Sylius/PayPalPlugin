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
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class CaptureAction implements ActionInterface
{
    /** @var ClientInterface */
    private $httpClient;

    /** @var string */
    private $facilitatorUrl;

    public function __construct(ClientInterface $httpClient, string $facilitatorUrl)
    {
        $this->httpClient = $httpClient;
        $this->facilitatorUrl = $facilitatorUrl;
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
            $this->facilitatorUrl.'/create-order',
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
        $payment->setDetails(['status' => $content['status'], 'order_id' => $content['order_id']]);
    }

    public function supports($request): bool
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof PaymentInterface
        ;
    }
}
