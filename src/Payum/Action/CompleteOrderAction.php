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
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Payum\Request\CompleteOrder;

final class CompleteOrderAction implements ActionInterface
{
    /** @var ClientInterface */
    private $httpClient;

    public function __construct(ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /** @param CompleteOrder $request */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getModel();
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        $config = $paymentMethod->getGatewayConfig()->getConfig();

        $response = $this->httpClient->request(
            'POST',
            'https://api.sandbox.paypal.com/v1/oauth2/token',
            [
                'auth' => [$config['client_id'], $config['client_secret']],
                'form_params' => ['grant_type' => 'client_credentials'],
            ]
        );

        /** @var array $content */
        $content = json_decode($response->getBody()->getContents(), true);

        $response = $this->httpClient->request(
            'POST',
            sprintf('https://api.sandbox.paypal.com/v2/checkout/orders/%s/capture', $request->getOrderId()),
            [
                'headers' => [
                    'Authorization' => 'Bearer '.$content['access_token'],
                    'PayPal-Partner-Attribution-Id' => 'sylius-ppcp4p-bn-code',
                    'Content-Type' => 'application/json',
                ],
            ]
        );

        /** @var array $content */
        $content = json_decode($response->getBody()->getContents(), true);

        if ($content['status'] === 'COMPLETED') {
            $payment->setDetails([
                'status' => StatusAction::STATUS_COMPLETED,
                'paypal_order_id' => $content['id'],
            ]);
        }

    }

    public function supports($request): bool
    {
        return
            $request instanceof CompleteOrder &&
            $request->getModel() instanceof PaymentInterface
        ;
    }
}
