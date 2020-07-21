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

use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\AuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CreateOrderApiInterface;

final class CaptureAction implements ActionInterface
{
    /** @var AuthorizeClientApiInterface */
    private $authorizeClientApi;

    /** @var CreateOrderApiInterface */
    private $createOrderApi;

    public function __construct(
        AuthorizeClientApiInterface $authorizeClientApi,
        CreateOrderApiInterface $createOrderApi
    ) {
        $this->authorizeClientApi = $authorizeClientApi;
        $this->createOrderApi = $createOrderApi;
    }

    /** @param Capture $request */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getModel();
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface $gatewayConfig */
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        $config = $gatewayConfig->getConfig();

        $token = $this
            ->authorizeClientApi
            ->authorize((string) $config['client_id'], (string) $config['client_secret'])
        ;
        $content = $this->createOrderApi->create($token, $payment);

        if ($content['status'] === 'CREATED') {
            $payment->setDetails([
                'status' => StatusAction::STATUS_CREATED,
                'paypal_order_id' => $content['id'],
            ]);
        }
    }

    public function supports($request): bool
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof PaymentInterface
        ;
    }
}
